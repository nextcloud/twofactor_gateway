<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway;

class GatewayInstanceViewFactory {
	public function __construct(
		private GatewayFieldSanitizer $fieldSanitizer,
	) {
	}

	/**
	 * @param list<array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}> $instances
	 * @return array<string, mixed>
	 */
	public function createGatewayEntry(IGateway $gateway, array $instances, GatewayViewScope $scope): array {
		$settings = $gateway->getSettings();
		$entry = [
			'id' => $settings->id ?? $gateway->getProviderId(),
			'name' => $settings->name,
			'instructions' => $settings->instructions,
			'allowMarkdown' => $settings->allowMarkdown,
			'fields' => array_map(
				static fn (FieldDefinition $field): array => $field->jsonSerialize(),
				$this->fieldSanitizer->filterFields($settings->fields, $scope),
			),
			'instances' => array_values(array_map(
				fn (array $instance): array => $this->createInstanceView($gateway, $instance, $scope),
				$instances,
			)),
		];

		if (!($gateway instanceof IProviderCatalogGateway)) {
			return $entry;
		}

		$providerSelector = $this->fieldSanitizer->filterFields([$gateway->getProviderSelectorField()], $scope);
		if ($providerSelector !== []) {
			$entry['providerSelector'] = $providerSelector[0]->jsonSerialize();
		}

		$entry['providerCatalog'] = array_map(
			fn (array $provider): array => [
				'id' => (string)($provider['id'] ?? ''),
				'name' => (string)($provider['name'] ?? ''),
				'fields' => array_map(
					static fn (FieldDefinition $field): array => $field->jsonSerialize(),
					$this->fieldSanitizer->filterFields($this->onlyFieldDefinitions($provider['fields'] ?? []), $scope),
				),
			],
			$gateway->getProviderCatalog(),
		);

		return $entry;
	}

	/**
	 * @param array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int} $instance
	 * @return array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}
	 */
	public function createInstanceView(IGateway $gateway, array $instance, GatewayViewScope $scope): array {
		$record = GatewayInstanceRecord::fromArray($instance);
		$fields = $this->resolveInstanceFields($gateway, $record->config);

		return [
			'id' => $record->id,
			'label' => $record->label,
			'default' => $record->default,
			'createdAt' => $record->createdAt,
			'config' => $this->fieldSanitizer->sanitizeConfig($record->config, $fields, $scope),
			'isComplete' => $record->isComplete,
			'groupIds' => $record->groupIds,
			'priority' => $record->priority,
		];
	}

	/**
	 * @param array<string, string> $config
	 * @return list<FieldDefinition>
	 */
	private function resolveInstanceFields(IGateway $gateway, array $config): array {
		$settings = $gateway->getSettings();
		if (!($gateway instanceof IProviderCatalogGateway)) {
			return array_values($settings->fields);
		}

		$selector = $gateway->getProviderSelectorField();
		$fields = [$selector];
		$selectedProvider = trim((string)($config[$selector->field] ?? ''));
		if ($selectedProvider === '' && count($gateway->getProviderCatalog()) === 1) {
			$selectedProvider = (string)($gateway->getProviderCatalog()[0]['id'] ?? '');
		}

		if ($selectedProvider === '') {
			return $fields;
		}

		foreach ($gateway->getProviderCatalog() as $provider) {
			if ((string)($provider['id'] ?? '') !== $selectedProvider) {
				continue;
			}

			return array_values(array_merge($fields, $this->onlyFieldDefinitions($provider['fields'] ?? [])));
		}

		return $fields;
	}

	/**
	 * @param list<mixed> $fields
	 * @return list<FieldDefinition>
	 */
	private function onlyFieldDefinitions(array $fields): array {
		return array_values(array_filter(
			$fields,
			static fn ($field): bool => $field instanceof FieldDefinition,
		));
	}
}
