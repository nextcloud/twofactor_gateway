<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp;

use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IConfigurationChangeAwareGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IDefaultInstanceAwareGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IInteractiveSetupGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway;
use OCA\TwoFactorGateway\Provider\Gateway\ITestResultEnricher;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\IAppConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Gateway extends AGateway implements IConfigurationChangeAwareGateway, IProviderCatalogGateway, IInteractiveSetupGateway, IDefaultInstanceAwareGateway, ITestResultEnricher {
	public function __construct(
		public IAppConfig $appConfig,
		private Factory $whatsAppProviderFactory,
	) {
		parent::__construct($appConfig);
	}

	#[\Override]
	public function createSettings(): Settings {
		$fields = [$this->getProviderSelectorField()];
		$allowMarkdown = true;
		$instructions = '';

		try {
			$driverSettings = $this->getProvider()->getSettings();
			$allowMarkdown = $driverSettings->allowMarkdown;
			$instructions = $driverSettings->instructions;
			foreach ($driverSettings->fields as $field) {
				if ($field->field === 'session_id' || $field->field === $this->getProviderSelectorField()->field) {
					continue;
				}
				$fields[] = $field;
			}
		} catch (ConfigurationException) {
			// Keep selector field only when provider config is not available yet.
		}

		return new Settings(
			name: 'WhatsApp',
			id: 'whatsapp',
			allowMarkdown: $allowMarkdown,
			instructions: $instructions,
			fields: $fields,
		);
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		$this->getProvider()->send($identifier, $message, $extra);
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		return $this->getProvider()->cliConfigure($input, $output);
	}

	#[\Override]
	public function getProviderSelectorField(): FieldDefinition {
		return new FieldDefinition(
			field: 'provider',
			prompt: 'WhatsApp provider',
			default: 'gowhatsapp',
			optional: false,
			hidden: true,
		);
	}

	#[\Override]
	public function getProviderCatalog(): array {
		$selectorField = $this->getProviderSelectorField()->field;
		$catalog = [];
		foreach ($this->whatsAppProviderFactory->getFqcnList() as $fqcn) {
			$provider = $this->whatsAppProviderFactory->get($fqcn);
			$settings = $provider->getSettings();
			$catalog[] = [
				'id' => (string)($settings->id ?? $provider->getProviderId()),
				'name' => $settings->name,
				'fields' => array_values(array_filter(
					$settings->fields,
					static fn (FieldDefinition $field): bool => $field->field !== 'session_id' && $field->field !== $selectorField,
				)),
			];
		}

		return $catalog;
	}

	#[\Override]
	public function interactiveSetupStart(array $input): array {
		$provider = $this->getProvider();
		if ($provider instanceof IInteractiveSetupGateway) {
			return $provider->interactiveSetupStart($input);
		}

		return [
			'status' => 'error',
			'message' => 'Interactive setup is not supported for the selected WhatsApp provider.',
		];
	}

	#[\Override]
	public function interactiveSetupStep(string $sessionId, string $action, array $input = []): array {
		$provider = $this->getProvider();
		if ($provider instanceof IInteractiveSetupGateway) {
			return $provider->interactiveSetupStep($sessionId, $action, $input);
		}

		return [
			'status' => 'error',
			'message' => 'Interactive setup is not supported for the selected WhatsApp provider.',
		];
	}

	#[\Override]
	public function interactiveSetupCancel(string $sessionId): array {
		$provider = $this->getProvider();
		if ($provider instanceof IInteractiveSetupGateway) {
			return $provider->interactiveSetupCancel($sessionId);
		}

		return [
			'status' => 'ok',
			'message' => 'No interactive setup session for the selected WhatsApp provider.',
		];
	}

	#[\Override]
	public function onDefaultInstanceActivated(): void {
		$provider = $this->getProvider();
		if ($provider instanceof IDefaultInstanceAwareGateway) {
			$provider->onDefaultInstanceActivated();
		}
	}

	#[\Override]
	public function enrichTestResult(array $instanceConfig, string $identifier = ''): array {
		$providerName = trim((string)($instanceConfig['provider'] ?? ''));
		try {
			$provider = $this->getProvider($providerName);
		} catch (ConfigurationException) {
			return [];
		}

		if ($provider instanceof ITestResultEnricher) {
			return $provider->enrichTestResult($instanceConfig, $identifier);
		}

		return [];
	}

	#[\Override]
	public function syncAfterConfigurationChange(): void {
		$provider = $this->getProvider();
		if ($provider instanceof IConfigurationChangeAwareGateway) {
			$provider->syncAfterConfigurationChange();
		}
	}

	private function getProvider(string $providerName = ''): AGateway {
		if ($providerName === '' && is_array($this->runtimeConfig)) {
			$providerName = trim((string)($this->runtimeConfig['provider'] ?? ''));
		}
		if ($providerName === '') {
			$providerName = $this->getProviderSelectorField()->default ?? 'gowhatsapp';
		}

		try {
			$provider = $this->whatsAppProviderFactory->get($providerName);
		} catch (\Throwable) {
			throw new ConfigurationException('Invalid WhatsApp provider: ' . $providerName);
		}

		if (is_array($this->runtimeConfig)) {
			return $provider->withRuntimeConfig($this->runtimeConfig);
		}

		return $provider;
	}
}
