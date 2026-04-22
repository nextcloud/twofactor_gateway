<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\GatewayInstanceNotFoundException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\IAppConfig;

/**
 * Manages multiple named configuration instances per gateway, stored in IAppConfig.
 *
 * Storage key patterns:
 *   Registry : "instances:{gatewayId}"             → JSON array of InstanceRecord
 *   Fields   : "{gatewayId}:{instanceId}:{field}"  → string value
 */
class GatewayConfigService {
	public function __construct(
		private IAppConfig $appConfig,
		private GatewayFactory $gatewayFactory,
	) {
	}

	/**
	 * Return the full list of available gateways together with their configured
	 * instances, ready to be serialised to the admin frontend.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function getGatewayList(): array {
		$result = [];
		foreach ($this->gatewayFactory->getFqcnList() as $fqcn) {
			$gateway = $this->gatewayFactory->get($fqcn);
			$settings = $gateway->getSettings();
			$gatewayEntry = [
				'id' => $settings->id ?? $gateway->getProviderId(),
				'name' => $settings->name,
				'instructions' => $settings->instructions,
				'allowMarkdown' => $settings->allowMarkdown,
				'fields' => array_map(fn ($f) => $f->jsonSerialize(), $settings->fields),
				'instances' => $this->listInstances($gateway),
			];

			if ($gateway instanceof IProviderCatalogGateway) {
				$gatewayEntry['providerSelector'] = $gateway->getProviderSelectorField()->jsonSerialize();
				$gatewayEntry['providerCatalog'] = array_map(
					static fn (array $provider): array => [
						'id' => (string)($provider['id'] ?? ''),
						'name' => (string)($provider['name'] ?? ''),
						'fields' => array_map(
							static fn ($field): array => $field->jsonSerialize(),
							$provider['fields'] ?? [],
						),
					],
					$gateway->getProviderCatalog(),
				);
			}

			$result[] = $gatewayEntry;
		}
		return $result;
	}

	/**
	 * List all configured instances for a gateway.
	 *
	 * @return list<array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}>
	 */
	public function listInstances(IGateway $gateway): array {
		$registry = $this->loadRegistry($gateway->getProviderId());
		return array_values(array_map(fn (array $meta) => $this->buildInstanceRecord($gateway, $meta), $registry));
	}

	/**
	 * Return a single named instance.
	 *
	 * @return array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}
	 * @throws GatewayInstanceNotFoundException
	 */
	public function getInstance(IGateway $gateway, string $instanceId): array {
		$meta = $this->findOrFail($gateway, $instanceId);
		return $this->buildInstanceRecord($gateway, $meta);
	}

	/**
	 * Create a new named configuration instance.
	 *
	 * The very first instance for a gateway automatically becomes the default.
	 *
	 * @param array<string, string> $config
	 * @param list<string> $groupIds
	 * @param int $priority
	 * @return array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}
	 */
	public function createInstance(IGateway $gateway, string $label, array $config, array $groupIds = [], int $priority = 0): array {
		$gatewayId = $gateway->getProviderId();
		$registry = $this->loadRegistry($gatewayId);

		$isFirst = empty($registry);
		$instanceId = $this->generateId();
		$this->storeFieldValues($gatewayId, $instanceId, $gateway, $config);

		$meta = [
			'id' => $instanceId,
			'label' => $label,
			'default' => $isFirst,
			'createdAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
			'groupIds' => $this->normalizeGroupIds($groupIds),
			'priority' => $this->normalizePriority($priority),
		];
		$registry[] = $meta;
		$this->saveRegistry($gatewayId, $registry);

		return $this->buildInstanceRecord($gateway, $meta);
	}

	/**
	 * Update an existing instance's label and/or configuration.
	 *
	 * @param array<string, string> $config
	 * @param list<string> $groupIds
	 * @param int $priority
	 * @return array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}
	 * @throws GatewayInstanceNotFoundException
	 */
	public function updateInstance(IGateway $gateway, string $instanceId, string $label, array $config, array $groupIds = [], int $priority = 0): array {
		$gatewayId = $gateway->getProviderId();
		$registry = $this->loadRegistry($gatewayId);

		$updated = false;
		foreach ($registry as &$meta) {
			if ($meta['id'] === $instanceId) {
				$meta['label'] = $label;
				$meta['groupIds'] = $this->normalizeGroupIds($groupIds);
				$meta['priority'] = $this->normalizePriority($priority);
				$this->storeFieldValues($gatewayId, $instanceId, $gateway, $config);
				$updated = true;
				break;
			}
		}
		unset($meta);

		if (!$updated) {
			throw new GatewayInstanceNotFoundException($gatewayId, $instanceId);
		}

		$this->saveRegistry($gatewayId, $registry);

		return $this->getInstance($gateway, $instanceId);
	}

	/**
	 * Delete an instance.
	 *
	 * If the deleted instance was the default and another instance exists, the
	 * registry may temporarily have no default until it is loaded again.
	 * During the next load, registry normalization assigns a default instance
	 * when the registry is non-empty.
	 *
	 * @throws GatewayInstanceNotFoundException
	 */
	public function deleteInstance(IGateway $gateway, string $instanceId): void {
		$gatewayId = $gateway->getProviderId();
		$registry = $this->loadRegistry($gatewayId);

		$found = false;
		$registry = array_values(array_filter($registry, function (array $meta) use ($instanceId, &$found): bool {
			if ($meta['id'] === $instanceId) {
				$found = true;
				return false;
			}
			return true;
		}));

		if (!$found) {
			throw new GatewayInstanceNotFoundException($gatewayId, $instanceId);
		}

		$this->deleteFieldValues($gatewayId, $instanceId, $gateway);
		$this->saveRegistry($gatewayId, $registry);
	}

	/**
	 * Promote an instance to the default.
	 *
	 * @throws GatewayInstanceNotFoundException
	 */
	public function setDefaultInstance(IGateway $gateway, string $instanceId): void {
		$gatewayId = $gateway->getProviderId();
		$registry = $this->loadRegistry($gatewayId);

		$found = false;
		foreach ($registry as &$meta) {
			if ($meta['id'] === $instanceId) {
				$meta['default'] = true;
				$found = true;
			} else {
				$meta['default'] = false;
			}
		}
		unset($meta);

		if (!$found) {
			throw new GatewayInstanceNotFoundException($gatewayId, $instanceId);
		}

		$this->saveRegistry($gatewayId, $registry);
	}

	/**
	 * Create a default instance from a gateway's legacy primary configuration.
	 *
	 * Returns true if a new instance was created, false if the gateway was not
	 * fully configured (isComplete() returns false) or already has instances.
	 */
	public function createDefaultInstanceFromPrimaryConfiguration(IGateway $gateway): bool {
		if (!$gateway->isComplete()) {
			return false;
		}

		$gatewayId = $gateway->getProviderId();
		if (!empty($this->loadRegistry($gatewayId))) {
			return false;
		}

		$config = $gateway->getConfiguration();
		$this->createInstance($gateway, 'Default', $config);
		return true;
	}

	private function registryKey(string $gatewayId): string {
		return 'instances:' . $gatewayId;
	}

	/**
	 * @return list<array{id: string, label: string, default: bool, createdAt: string, groupIds?: list<string>, priority?: int}>
	 */
	private function loadRegistry(string $gatewayId): array {
		$raw = $this->appConfig->getValueString(Application::APP_ID, $this->registryKey($gatewayId), '[]');
		$data = json_decode($raw, true);
		if (!is_array($data)) {
			return [];
		}
		/** @var list<array{id: string, label: string, default: bool, createdAt: string}> */
		$result = array_values($data);
		[$normalized, $changed] = $this->normalizeRegistryDefaults($result);
		if ($changed) {
			$this->saveRegistry($gatewayId, $normalized);
		}
		return $normalized;
	}

	/**
	 * @param list<array{id: string, label: string, default: bool, createdAt: string, groupIds?: list<string>, priority?: int}> $registry
	 * @return array{0: list<array{id: string, label: string, default: bool, createdAt: string, groupIds?: list<string>, priority?: int}>, 1: bool}
	 */
	private function normalizeRegistryDefaults(array $registry): array {
		if ($registry === []) {
			return [$registry, false];
		}

		$normalized = [];
		$changed = false;
		$hasDefault = false;

		foreach ($registry as $meta) {
			$isDefault = (bool)($meta['default'] ?? false);
			if ($isDefault) {
				if ($hasDefault) {
					$isDefault = false;
					$changed = true;
				} else {
					$hasDefault = true;
				}
			}

			if (($meta['default'] ?? false) !== $isDefault) {
				$changed = true;
			}

			$meta['default'] = $isDefault;
			$normalized[] = $meta;
		}

		if (!$hasDefault) {
			$normalized[0]['default'] = true;
			$changed = true;
		}

		return [$normalized, $changed];
	}

	/**
	 * @param list<array{id: string, label: string, default: bool, createdAt: string, groupIds?: list<string>, priority?: int}> $registry
	 */
	private function saveRegistry(string $gatewayId, array $registry): void {
		$this->appConfig->setValueString(Application::APP_ID, $this->registryKey($gatewayId), json_encode(array_values($registry), JSON_THROW_ON_ERROR));
	}

	/** @throws GatewayInstanceNotFoundException */
	private function findOrFail(IGateway $gateway, string $instanceId): array {
		foreach ($this->loadRegistry($gateway->getProviderId()) as $meta) {
			if ($meta['id'] === $instanceId) {
				return $meta;
			}
		}
		throw new GatewayInstanceNotFoundException($gateway->getProviderId(), $instanceId);
	}

	private function instanceFieldKey(string $gatewayId, string $instanceId, string $fieldName): string {
		return $gatewayId . ':' . $instanceId . ':' . $fieldName;
	}

	/**
	 * @param array<string, string> $config
	 */
	private function storeFieldValues(string $gatewayId, string $instanceId, IGateway $gateway, array $config): void {
		$fieldNames = array_map(
			static fn (FieldDefinition $field): string => $field->field,
			$gateway->getSettings()->fields,
		);

		if ($gateway instanceof IProviderCatalogGateway) {
			$selector = $gateway->getProviderSelectorField();
			$fieldNames[] = $selector->field;

			$selectedProvider = trim((string)($config[$selector->field]
				?? $this->appConfig->getValueString(
					Application::APP_ID,
					$this->instanceFieldKey($gatewayId, $instanceId, $selector->field),
					$selector->default,
				)));
			if ($selectedProvider !== '') {
				$config[$selector->field] = $selectedProvider;
				foreach ($gateway->getProviderCatalog() as $provider) {
					if ((string)($provider['id'] ?? '') !== $selectedProvider) {
						continue;
					}

					foreach ($provider['fields'] ?? [] as $providerField) {
						if ($providerField instanceof FieldDefinition) {
							$fieldNames[] = $providerField->field;
						}
					}
					break;
				}
			}
		}

		foreach (array_values(array_unique($fieldNames)) as $fieldName) {
			if (!array_key_exists($fieldName, $config)) {
				continue;
			}

			$this->appConfig->setValueString(
				Application::APP_ID,
				$this->instanceFieldKey($gatewayId, $instanceId, $fieldName),
				(string)$config[$fieldName],
			);
		}
	}

	private function deleteFieldValues(string $gatewayId, string $instanceId, IGateway $gateway): void {
		$fieldNames = array_map(
			static fn (FieldDefinition $field): string => $field->field,
			$gateway->getSettings()->fields,
		);

		if ($gateway instanceof IProviderCatalogGateway) {
			$fieldNames[] = $gateway->getProviderSelectorField()->field;
			foreach ($gateway->getProviderCatalog() as $provider) {
				foreach ($provider['fields'] ?? [] as $providerField) {
					if ($providerField instanceof FieldDefinition) {
						$fieldNames[] = $providerField->field;
					}
				}
			}
		}

		foreach (array_values(array_unique($fieldNames)) as $fieldName) {
			$this->appConfig->deleteKey(
				Application::APP_ID,
				$this->instanceFieldKey($gatewayId, $instanceId, $fieldName),
			);
		}
	}

	/** @return array<string, string> */
	private function loadFieldValues(string $gatewayId, string $instanceId, Settings $settings): array {
		$config = [];
		foreach ($settings->fields as $field) {
			$config[$field->field] = $this->appConfig->getValueString(
				Application::APP_ID,
				$this->instanceFieldKey($gatewayId, $instanceId, $field->field),
				$field->default,
			);
		}
		return $config;
	}

	private function generateId(): string {
		return bin2hex(random_bytes(8));
	}

	/**
	 * @param array{id: string, label: string, default: bool, createdAt: string, groupIds?: list<string>, priority?: int} $meta
	 * @return array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}
	 */
	private function buildInstanceRecord(IGateway $gateway, array $meta): array {
		$settings = $gateway->getSettings();
		$config = $this->loadFieldValues($gateway->getProviderId(), $meta['id'], $settings);
		$completenessSettings = $settings;

		if ($gateway instanceof IProviderCatalogGateway) {
			$selector = $gateway->getProviderSelectorField();
			if (!array_key_exists($selector->field, $config)) {
				$config[$selector->field] = $this->appConfig->getValueString(
					Application::APP_ID,
					$this->instanceFieldKey($gateway->getProviderId(), $meta['id'], $selector->field),
					$selector->default,
				);
			}

			$selectedProvider = trim((string)$config[$selector->field]);

			if ($selectedProvider !== '') {
				foreach ($gateway->getProviderCatalog() as $provider) {
					if ((string)($provider['id'] ?? '') !== $selectedProvider) {
						continue;
					}

					$providerFields = array_values(array_filter(
						$provider['fields'] ?? [],
						static fn ($field): bool => $field instanceof FieldDefinition,
					));

					foreach ($providerFields as $field) {
						if (!array_key_exists($field->field, $config)) {
							$config[$field->field] = $this->appConfig->getValueString(
								Application::APP_ID,
								$this->instanceFieldKey($gateway->getProviderId(), $meta['id'], $field->field),
								$field->default,
							);
						}
					}

					$completenessSettings = new Settings(
						name: $settings->name,
						id: $settings->id,
						allowMarkdown: $settings->allowMarkdown,
						instructions: $settings->instructions,
						fields: array_values(array_merge([$selector], $providerFields)),
					);
					break;
				}
			}
		}

		$isComplete = $this->isInstanceComplete($completenessSettings, $config);
		$groupIds = is_array($meta['groupIds'] ?? null) ? $this->normalizeGroupIds($meta['groupIds']) : [];
		$priority = $this->normalizePriority($meta['priority'] ?? 0);
		return [
			'id' => $meta['id'],
			'label' => $meta['label'],
			'default' => $meta['default'],
			'createdAt' => $meta['createdAt'],
			'config' => $config,
			'isComplete' => $isComplete,
			'groupIds' => $groupIds,
			'priority' => $priority,
		];
	}

	/** @param list<string> $groupIds
	 * @return list<string>
	 */
	private function normalizeGroupIds(array $groupIds): array {
		$normalized = array_values(array_unique(array_filter(array_map(
			static fn ($groupId): string => trim((string)$groupId),
			$groupIds,
		), static fn (string $groupId): bool => $groupId !== '')));
		sort($normalized);
		return $normalized;
	}

	private function normalizePriority(mixed $priority): int {
		if (!is_numeric($priority)) {
			return 0;
		}

		return (int)$priority;
	}

	/** @param array<string, string> $config */
	private function isInstanceComplete(Settings $settings, array $config): bool {
		foreach ($settings->fields as $field) {
			if ($field->optional) {
				continue;
			}
			if (!isset($config[$field->field]) || $config[$field->field] === '') {
				return false;
			}
		}
		return true;
	}
}
