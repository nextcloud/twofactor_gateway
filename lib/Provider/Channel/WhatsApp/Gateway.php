<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\Gateway as GoWhatsAppGateway;
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
		private GoWhatsAppGateway $goWhatsAppGateway,
	) {
		parent::__construct($appConfig);
	}

	#[\Override]
	public function createSettings(): Settings {
		$driverSettings = $this->goWhatsAppGateway->getSettings();
		$fields = array_values(array_filter(
			$driverSettings->fields,
			static fn (FieldDefinition $field): bool => $field->field !== 'session_id',
		));

		return new Settings(
			name: 'WhatsApp',
			id: 'whatsapp',
			allowMarkdown: $driverSettings->allowMarkdown,
			instructions: $driverSettings->instructions,
			fields: $fields,
		);
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		$this->delegateDriver()->send($identifier, $message, $extra);
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		return $this->delegateDriver()->cliConfigure($input, $output);
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
		$driverSettings = $this->goWhatsAppGateway->getSettings();
		$fields = array_values(array_filter(
			$driverSettings->fields,
			static fn (FieldDefinition $field): bool => $field->field !== 'session_id',
		));

		return [[
			'id' => 'gowhatsapp',
			'name' => 'WhatsApp',
			'fields' => $fields,
		]];
	}

	#[\Override]
	public function interactiveSetupStart(array $input): array {
		return $this->delegateDriver()->interactiveSetupStart($input);
	}

	#[\Override]
	public function interactiveSetupStep(string $sessionId, string $action, array $input = []): array {
		return $this->delegateDriver()->interactiveSetupStep($sessionId, $action, $input);
	}

	#[\Override]
	public function interactiveSetupCancel(string $sessionId): array {
		return $this->delegateDriver()->interactiveSetupCancel($sessionId);
	}

	#[\Override]
	public function onDefaultInstanceActivated(): void {
		$this->delegateDriver()->onDefaultInstanceActivated();
	}

	#[\Override]
	public function enrichTestResult(array $instanceConfig, string $identifier = ''): array {
		return $this->goWhatsAppGateway->enrichTestResult($instanceConfig, $identifier);
	}

	#[\Override]
	public function syncAfterConfigurationChange(): void {
		$this->delegateDriver()->syncAfterConfigurationChange();
	}

	private function delegateDriver(): GoWhatsAppGateway {
		if ($this->runtimeConfig === null && $this->settings === null) {
			return $this->goWhatsAppGateway;
		}

		$config = is_array($this->runtimeConfig) ? $this->runtimeConfig : $this->getConfiguration($this->getSettings());
		return $this->goWhatsAppGateway->withRuntimeConfig($config);
	}
}
