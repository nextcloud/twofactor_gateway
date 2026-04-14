<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\GatewayInstanceNotFoundException;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\IAppConfig;
use OCP\Security\ISecureRandom;

/**
 * Manages multiple named configuration instances per gateway, stored in IAppConfig.
 *
 * Storage key patterns:
 *   Registry : "instances:{gatewayId}"             → JSON array of InstanceRecord
 *   Fields   : "{gatewayId}:{instanceId}:{field}"  → string value
 *
 * Backward-compat:
 *   When an instance is set as default its field values are also mirrored to
 *   the legacy keys "{gatewayId}_{field}" so that the existing 2-FA flow and
 *   CLI commands keep working without any changes.
 */
class GatewayConfigService {
	public function __construct(
		private IAppConfig $appConfig,
		private GatewayFactory $gatewayFactory,
	) {
	}

	// ──────────────────────────────────────────────────────────────────────────
	//  Public API
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Return the full list of available gateways together with their configured
	 * instances, ready to be serialised to the admin frontend.
	 *
	 * @return list<array{id: string, name: string, instructions: string, allowMarkdown: bool, fields: list<mixed>, instances: list<mixed>}>
	 */
	public function getGatewayList(): array {
		$result = [];
		foreach ($this->gatewayFactory->getFqcnList() as $fqcn) {
			$gateway = $this->gatewayFactory->get($fqcn);
			$settings = $gateway->getSettings();
			$result[] = [
				'id' => $settings->id ?? $gateway->getProviderId(),
				'name' => $settings->name,
				'instructions' => $settings->instructions,
				'allowMarkdown' => $settings->allowMarkdown,
				'fields' => array_map(fn ($f) => $f->jsonSerialize(), $settings->fields),
				'instances' => $this->listInstances($gateway),
			];
		}
		return $result;
	}

	/**
	 * List all configured instances for a gateway.
	 *
	 * @return list<array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool}>
	 */
	public function listInstances(IGateway $gateway): array {
		$registry = $this->loadRegistry($gateway->getProviderId());
		return array_values(array_map(fn (array $meta) => $this->buildInstanceRecord($gateway, $meta), $registry));
	}

	/**
	 * Return a single named instance.
	 *
	 * @return array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool}
	 * @throws GatewayInstanceNotFoundException
	 */
	public function getInstance(IGateway $gateway, string $instanceId): array {
		$meta = $this->findOrFail($gateway, $instanceId);
		return $this->buildInstanceRecord($gateway, $meta);
	}

	/**
	 * Create a new named configuration instance.
	 *
	 * The very first instance for a gateway automatically becomes the default
	 * (and its values are mirrored to the legacy primary keys).
	 *
	 * @param array<string, string> $config
	 * @return array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool}
	 */
	public function createInstance(IGateway $gateway, string $label, array $config): array {
		$gatewayId = $gateway->getProviderId();
		$registry = $this->loadRegistry($gatewayId);

		$isFirst = empty($registry);
		$instanceId = $this->generateId();
		$this->storeFieldValues($gatewayId, $instanceId, $gateway->getSettings(), $config);

		$meta = [
			'id' => $instanceId,
			'label' => $label,
			'default' => $isFirst,
			'createdAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
		];
		$registry[] = $meta;
		$this->saveRegistry($gatewayId, $registry);

		if ($isFirst) {
			$this->mirrorToPrimaryKeys($gateway, $instanceId);
		}

		return $this->buildInstanceRecord($gateway, $meta);
	}

	/**
	 * Update an existing instance's label and/or configuration.
	 *
	 * @param array<string, string> $config
	 * @return array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool}
	 * @throws GatewayInstanceNotFoundException
	 */
	public function updateInstance(IGateway $gateway, string $instanceId, string $label, array $config): array {
		$gatewayId = $gateway->getProviderId();
		$registry = $this->loadRegistry($gatewayId);

		$updated = false;
		foreach ($registry as &$meta) {
			if ($meta['id'] === $instanceId) {
				$meta['label'] = $label;
				$this->storeFieldValues($gatewayId, $instanceId, $gateway->getSettings(), $config);
				if ($meta['default']) {
					$this->mirrorToPrimaryKeys($gateway, $instanceId);
				}
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
	 * When the deleted instance was the default, primary keys are cleared so the
	 * legacy 2-FA flow does not use stale configuration.
	 * If another instance exists, it does NOT automatically become default;
	 * the administrator must choose one explicitly.
	 *
	 * @throws GatewayInstanceNotFoundException
	 */
	public function deleteInstance(IGateway $gateway, string $instanceId): void {
		$gatewayId = $gateway->getProviderId();
		$registry = $this->loadRegistry($gatewayId);

		$wasDefault = false;
		$registry = array_values(array_filter($registry, function (array $meta) use ($instanceId, &$wasDefault): bool {
			if ($meta['id'] === $instanceId) {
				$wasDefault = $meta['default'];
				return false;
			}
			return true;
		}));

		if (!isset($wasDefault)) {
			throw new GatewayInstanceNotFoundException($gatewayId, $instanceId);
		}

		// Remove per-instance field keys
		$this->deleteFieldValues($gatewayId, $instanceId, $gateway->getSettings());

		if ($wasDefault) {
			$this->clearPrimaryKeys($gateway);
		}

		$this->saveRegistry($gatewayId, $registry);
	}

	/**
	 * Promote an instance to the default.
	 *
	 * Mirrors its values to the legacy primary keys so the 2-FA flow picks them up.
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
		$this->mirrorToPrimaryKeys($gateway, $instanceId);
	}

	// ──────────────────────────────────────────────────────────────────────────
	//  Internal helpers
	// ──────────────────────────────────────────────────────────────────────────

	private function registryKey(string $gatewayId): string {
		return 'instances:' . $gatewayId;
	}

	/**
	 * @return list<array{id: string, label: string, default: bool, createdAt: string}>
	 */
	private function loadRegistry(string $gatewayId): array {
		$raw = $this->appConfig->getValueString(Application::APP_ID, $this->registryKey($gatewayId), '[]');
		$data = json_decode($raw, true);
		return is_array($data) ? $data : [];
	}

	/**
	 * @param list<array{id: string, label: string, default: bool, createdAt: string}> $registry
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
	private function storeFieldValues(string $gatewayId, string $instanceId, Settings $settings, array $config): void {
		foreach ($settings->fields as $field) {
			if (isset($config[$field->field])) {
				$this->appConfig->setValueString(
					Application::APP_ID,
					$this->instanceFieldKey($gatewayId, $instanceId, $field->field),
					(string)$config[$field->field],
				);
			}
		}
	}

	private function deleteFieldValues(string $gatewayId, string $instanceId, Settings $settings): void {
		foreach ($settings->fields as $field) {
			$this->appConfig->deleteKey(
				Application::APP_ID,
				$this->instanceFieldKey($gatewayId, $instanceId, $field->field),
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

	/**
	 * Mirror an instance's values to the legacy primary keys used by TConfigurable and the CLI.
	 * Format: "{gatewayId}_{fieldName}"
	 */
	private function mirrorToPrimaryKeys(IGateway $gateway, string $instanceId): void {
		$gatewayId = $gateway->getProviderId();
		$settings = $gateway->getSettings();
		foreach ($settings->fields as $field) {
			$value = $this->appConfig->getValueString(
				Application::APP_ID,
				$this->instanceFieldKey($gatewayId, $instanceId, $field->field),
				$field->default,
			);
			$this->appConfig->setValueString(
				Application::APP_ID,
				$gatewayId . '_' . $field->field,
				$value,
			);
		}
	}

	/**
	 * Remove the legacy primary keys when no default instance exists.
	 */
	private function clearPrimaryKeys(IGateway $gateway): void {
		$gatewayId = $gateway->getProviderId();
		foreach ($gateway->getSettings()->fields as $field) {
			$this->appConfig->deleteKey(Application::APP_ID, $gatewayId . '_' . $field->field);
		}
	}

	private function generateId(): string {
		return bin2hex(random_bytes(8));
	}

	/**
	 * @param array{id: string, label: string, default: bool, createdAt: string} $meta
	 * @return array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool}
	 */
	private function buildInstanceRecord(IGateway $gateway, array $meta): array {
		$settings = $gateway->getSettings();
		$config = $this->loadFieldValues($gateway->getProviderId(), $meta['id'], $settings);
		$isComplete = $this->isInstanceComplete($settings, $config);
		return [
			'id' => $meta['id'],
			'label' => $meta['label'],
			'default' => $meta['default'],
			'createdAt' => $meta['createdAt'],
			'config' => $config,
			'isComplete' => $isComplete,
		];
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
