<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\AProvider;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IConfigurationChangeAwareGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IInteractiveSetupGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway;
use OCA\TwoFactorGateway\Provider\Gateway\ITestIdentifierNormalizer;
use OCA\TwoFactorGateway\Provider\Gateway\ITestResultEnricher;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Service\TelegramClientSessionMonitorJobManager;
use OCP\IAppConfig;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Gateway extends AGateway implements IProviderCatalogGateway, IInteractiveSetupGateway, ITestResultEnricher, IConfigurationChangeAwareGateway, ITestIdentifierNormalizer {

	public function __construct(
		public IAppConfig $appConfig,
		private Factory $telegramProviderFactory,
		private ?TelegramClientSessionMonitorJobManager $telegramClientSessionMonitorJobManager = null,
		private ?InteractiveSetupStateStore $interactiveSetupStateStore = null,
	) {
		parent::__construct($appConfig);
		$this->interactiveSetupStateStore ??= new InteractiveSetupStateStore($appConfig);
	}

	#[\Override]
	public function syncAfterConfigurationChange(): void {
		$this->telegramClientSessionMonitorJobManager?->sync();
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		$this->getProvider()->send($identifier, $message);
	}

	#[\Override]
	public function normalizeTestIdentifier(string $identifier): string {
		if ($identifier === '' || str_starts_with($identifier, '@') || str_starts_with($identifier, '+')) {
			return $identifier;
		}

		if (preg_match('/^-?\d+$/', $identifier) === 1) {
			return $identifier;
		}

		if (preg_match('/^[A-Za-z][A-Za-z0-9_]{2,}$/', $identifier) === 1) {
			return '@' . $identifier;
		}

		return $identifier;
	}

	#[\Override]
	public function getProviderSelectorField(): FieldDefinition {
		return new FieldDefinition(
			field: 'provider',
			prompt: 'Telegram provider',
			default: 'telegram_bot',
			optional: false,
			hidden: true,
		);
	}

	#[\Override]
	public function getProviderCatalog(): array {
		$catalog = [];
		foreach ($this->telegramProviderFactory->getFqcnList() as $fqcn) {
			$provider = $this->telegramProviderFactory->get($fqcn);
			$provider->setAppConfig($this->appConfig);
			$settings = $provider->getSettings();
			$catalog[] = [
				'id' => (string)($settings->id ?? $provider->getProviderId()),
				'name' => $settings->name,
				'fields' => array_values($settings->fields),
			];
		}

		return $catalog;
	}

	#[\Override]
	final public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$namespaces = $this->telegramProviderFactory->getFqcnList();
		$names = [];
		$providers = [];
		foreach ($namespaces as $ns) {
			$provider = $this->telegramProviderFactory->get($ns);
			$providers[] = $provider;
			$names[] = $provider->getSettings()->name;
		}

		$helper = new QuestionHelper();
		$choiceQuestion = new ChoiceQuestion('Please choose a Telegram provider:', $names);
		$name = $helper->ask($input, $output, $choiceQuestion);
		$selectedIndex = array_search($name, $names);

		$providers[$selectedIndex]->cliConfigure($input, $output);
		return 0;
	}

	#[\Override]
	public function createSettings(): Settings {
		$fields = [$this->getProviderSelectorField()];
		try {
			$providerSettings = $this->getProvider()->getSettings();
			foreach ($providerSettings->fields as $field) {
				if ($field->field === $this->getProviderSelectorField()->field) {
					continue;
				}
				$fields[] = $field;
			}
		} catch (ConfigurationException) {
		}

		return new Settings(
			name: 'Telegram',
			fields: $fields,
		);
	}

	#[\Override]
	public function isComplete(?Settings $settings = null): bool {
		if ($settings === null) {
			try {
				$provider = $this->getProvider();
			} catch (ConfigurationException) {
				return false;
			}
			$settings = $provider->getSettings();
		}

		$runtimeConfig = $this->resolveInstanceRuntimeConfig();
		if ($runtimeConfig !== null) {
			foreach ($settings->fields as $field) {
				if ($field->optional) {
					continue;
				}

				$value = trim((string)($runtimeConfig[$field->field] ?? $field->default));
				if ($value === '') {
					return false;
				}
			}

			return true;
		}

		return parent::isComplete($settings);
	}

	#[\Override]
	public function getConfiguration(?Settings $settings = null): array {
		try {
			$provider = $this->getProvider();
			$settings = $provider->getSettings();

			$runtimeConfig = $this->resolveInstanceRuntimeConfig();
			if ($runtimeConfig !== null) {
				$config = [];
				foreach ($settings->fields as $field) {
					$config[$field->field] = (string)($runtimeConfig[$field->field] ?? $field->default);
				}
			} else {
				$config = parent::getConfiguration($settings);
			}

			$config['provider'] = $settings->name;
			return $config;
		} catch (ConfigurationException|\Throwable $e) {
			$providers = [];
			foreach ($this->telegramProviderFactory->getFqcnList() as $fqcn) {
				$p = $this->telegramProviderFactory->get($fqcn);
				$p->setAppConfig($this->appConfig);
				$providerSettings = $p->getSettings();
				$providers[$providerSettings->name] = parent::getConfiguration($providerSettings);
			}
			return [
				'provider' => 'none',
				'available_providers' => $providers,
			];
		}
	}

	#[\Override]
	public function remove(?Settings $settings = null): void {
		foreach ($this->telegramProviderFactory->getFqcnList() as $fqcn) {
			$provider = $this->telegramProviderFactory->get($fqcn);
			$provider->setAppConfig($this->appConfig);
			$settings = $provider->getSettings();
			parent::remove($settings);
		}
	}

	/**
	 * @param array<string, string> $input
	 * @return array<string, mixed>
	 */
	#[\Override]
	public function interactiveSetupStart(array $input): array {
		$provider = trim((string)($input['provider'] ?? ''));
		if ($provider !== 'telegram_client') {
			return $this->withMessageType([
				'status' => 'error',
				'message' => 'Interactive setup is currently available only for Telegram Client API.',
			]);
		}

		$apiId = trim((string)($input['api_id'] ?? ''));
		$apiHash = trim((string)($input['api_hash'] ?? ''));
		$forceRelinkRaw = strtolower(trim((string)($input['force_relink'] ?? '0')));
		$forceRelink = in_array($forceRelinkRaw, ['1', 'true', 'yes', 'on'], true);
		$madelineLogEnabledRaw = strtolower(trim((string)($input['madeline_log_enabled'] ?? '0')));
		$madelineLogEnabled = in_array($madelineLogEnabledRaw, ['1', 'true', 'yes', 'on'], true);
		$madelineLogPath = trim((string)($input['madeline_log_path'] ?? ''));
		if ($apiId === '' || $apiHash === '') {
			return $this->withMessageType([
				'status' => 'error',
				'message' => 'Telegram api_id and api_hash are required to start interactive setup.',
			]);
		}

		$sessionId = $this->interactiveSetupStateStore->createSessionId();
		$state = [
			'provider' => $provider,
			'api_id' => $apiId,
			'api_hash' => $apiHash,
			'madeline_log_enabled' => $madelineLogEnabled ? '1' : '0',
			'madeline_log_path' => $madelineLogPath,
		];

		if ($forceRelink) {
			$this->resetInteractiveClientLogin($state);
		}
		$this->interactiveSetupStateStore->save($sessionId, $state);

		return $this->buildQrSetupResponse($sessionId, $state, 'Scan this QR code in Telegram to link the client session.');
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	#[\Override]
	public function interactiveSetupStep(string $sessionId, string $action, array $input = []): array {
		$state = $this->interactiveSetupStateStore->load($sessionId);
		if ($state === null) {
			return $this->withMessageType([
				'status' => 'error',
				'message' => 'Interactive setup session was not found or expired.',
			]);
		}

		return $this->withMessageType(match ($action) {
			'poll_login' => $this->interactiveSetupPollLogin($sessionId, $state),
			'submit_password' => $this->interactiveSetupSubmitPassword($sessionId, $state, $input),
			'cancel' => $this->interactiveSetupCancel($sessionId),
			default => [
				'status' => 'error',
				'message' => 'Unknown setup action: ' . $action,
			],
		});
	}

	/** @return array<string, mixed> */
	#[\Override]
	public function interactiveSetupCancel(string $sessionId): array {
		$state = $this->interactiveSetupStateStore->load($sessionId);
		if ($state !== null) {
			$this->resetInteractiveClientLogin($state);
		}
		$this->interactiveSetupStateStore->delete($sessionId);
		return $this->withMessageType([
			'status' => 'cancelled',
			'message' => 'Interactive setup cancelled.',
		]);
	}

	public function getProvider(string $providerName = ''): AProvider {
		$runtimeConfig = is_array($this->runtimeConfig) ? $this->runtimeConfig : null;
		if ($providerName === '' && is_array($this->runtimeConfig)) {
			$runtimeProvider = trim((string)($this->runtimeConfig['provider'] ?? ''));
			if ($runtimeProvider !== '') {
				$providerName = $runtimeProvider;
			}
		}

		if ($providerName !== '') {
			$provider = $this->telegramProviderFactory->get($providerName);
			if ($runtimeConfig !== null) {
				return $provider->withRuntimeConfig($runtimeConfig);
			}

			return $provider;
		}

		$providerName = $this->appConfig->getValueString(Application::APP_ID, 'telegram_provider_name');
		if ($providerName !== '') {
			$provider = $this->telegramProviderFactory->get($providerName);
			if ($runtimeConfig !== null) {
				return $provider->withRuntimeConfig($runtimeConfig);
			}

			return $provider;
		}

		$instanceRuntimeConfig = $this->resolveDefaultInstanceConfig();
		if ($instanceRuntimeConfig === null) {
			throw new ConfigurationException();
		}

		$instanceProviderName = trim((string)($instanceRuntimeConfig['provider'] ?? ''));
		if ($instanceProviderName === '') {
			throw new ConfigurationException();
		}

		$provider = $this->telegramProviderFactory->get($instanceProviderName);
		return $provider->withRuntimeConfig($instanceRuntimeConfig);
	}

	/** @return array<string, string>|null */
	private function resolveInstanceRuntimeConfig(): ?array {
		if (is_array($this->runtimeConfig)) {
			return $this->runtimeConfig;
		}

		$providerName = trim($this->appConfig->getValueString(Application::APP_ID, 'telegram_provider_name'));
		if ($providerName !== '') {
			return null;
		}

		return $this->resolveDefaultInstanceConfig();
	}

	/** @return array<string, string>|null */
	private function resolveDefaultInstanceConfig(): ?array {
		$registryRaw = $this->appConfig->getValueString(Application::APP_ID, 'instances:telegram', '[]');
		$registry = json_decode($registryRaw, true);
		if (!is_array($registry)) {
			return null;
		}

		$defaultInstanceId = '';
		foreach ($registry as $instanceMeta) {
			if (!is_array($instanceMeta) || !($instanceMeta['default'] ?? false)) {
				continue;
			}

			$defaultInstanceId = trim((string)($instanceMeta['id'] ?? ''));
			if ($defaultInstanceId !== '') {
				break;
			}
		}

		if ($defaultInstanceId === '') {
			return null;
		}

		$selector = $this->getProviderSelectorField();
		$providerId = trim($this->appConfig->getValueString(
			Application::APP_ID,
			'telegram:' . $defaultInstanceId . ':' . $selector->field,
			$selector->default,
		));
		if ($providerId === '') {
			return null;
		}

		$provider = $this->telegramProviderFactory->get($providerId);
		$provider->setAppConfig($this->appConfig);

		$config = [
			$selector->field => $providerId,
		];

		foreach ($provider->getSettings()->fields as $field) {
			$config[$field->field] = $this->appConfig->getValueString(
				Application::APP_ID,
				'telegram:' . $defaultInstanceId . ':' . $field->field,
				$field->default,
			);
		}

		return $config;
	}

	public function setProvider(string $provider): void {
		$this->appConfig->setValueString(Application::APP_ID, 'telegram_provider_name', $provider);
	}

	/**
	 * @param array<string, mixed> $state
	 * @return array<string, mixed>
	 */
	private function interactiveSetupPollLogin(string $sessionId, array $state): array {
		// Grace period: give background password submission time to start before polling session
		$passwordSubmittedAt = (int)($state['passwordSubmittedAt'] ?? 0);
		if ($passwordSubmittedAt > 0 && (time() - $passwordSubmittedAt) < 5) {
			return $this->withMessageType([
				'status' => 'pending',
				'sessionId' => $sessionId,
				'step' => 'password_polling',
				'message' => 'Submitting password to Telegram…',
			]);
		}

		$clientProvider = $this->resolveInteractiveClientProvider($state);
		if ($clientProvider === null || !is_callable([$clientProvider, 'fetchLoggedInAccountInfo'])) {
			return [
				'status' => 'error',
				'message' => 'Unable to initialize Telegram Client provider for interactive setup.',
			];
		}

		/** @var callable(): array<string, string> $fetchAccountInfo */
		$fetchAccountInfo = [$clientProvider, 'fetchLoggedInAccountInfo'];
		$accountInfo = $fetchAccountInfo();
		if ($accountInfo !== []) {
			$this->interactiveSetupStateStore->delete($sessionId);
			return [
				'status' => 'done',
				'message' => 'Telegram Client login completed successfully.',
				'config' => [
					'provider' => 'telegram_client',
					'api_id' => (string)($state['api_id'] ?? ''),
					'api_hash' => (string)($state['api_hash'] ?? ''),
					'madeline_log_enabled' => (string)($state['madeline_log_enabled'] ?? '0'),
					'madeline_log_path' => (string)($state['madeline_log_path'] ?? ''),
				],
				'data' => [
					'account' => $accountInfo,
				],
			];
		}

		return $this->buildQrSetupResponse($sessionId, $state, 'Waiting for Telegram QR scan confirmation.');
	}

	/**
	 * @param array<string, mixed> $state
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function interactiveSetupSubmitPassword(string $sessionId, array $state, array $input): array {
		$password = (string)($input['password'] ?? '');
		if ($password === '') {
			return [
				'status' => 'error',
				'message' => 'Telegram 2FA password is required.',
			];
		}

		$clientProvider = $this->resolveInteractiveClientProvider($state);
		if ($clientProvider === null) {
			return [
				'status' => 'error',
				'message' => 'Unable to initialize Telegram Client provider for interactive setup.',
			];
		}

		// Async path: run CLI in background and poll for result
		if (method_exists($clientProvider, 'startCompleteTwoFactorLoginBackground')) {
			/** @var callable(string): void $startBackground */
			$startBackground = [$clientProvider, 'startCompleteTwoFactorLoginBackground'];
			$startBackground($password);
			$state['passwordSubmittedAt'] = time();
			unset($state['passwordPollAttempts']);
			$this->interactiveSetupStateStore->save($sessionId, $state);
			return $this->withMessageType([
				'status' => 'pending',
				'sessionId' => $sessionId,
				'step' => 'password_polling',
				'message' => 'Password submitted. Verifying with Telegram…',
			]);
		}

		// Fallback: synchronous (for providers without background support)
		if (!method_exists($clientProvider, 'completeTwoFactorLogin')) {
			return [
				'status' => 'error',
				'message' => 'Unable to initialize Telegram Client provider for interactive setup.',
			];
		}

		/** @var callable(string): array<string, mixed> $completeTwoFactorLogin */
		$completeTwoFactorLogin = [$clientProvider, 'completeTwoFactorLogin'];
		$result = $completeTwoFactorLogin($password);
		if ((string)($result['status'] ?? '') === 'error') {
			$errorMessage = trim((string)($result['message'] ?? ''));
			if (stripos($errorMessage, 'AUTH_KEY_UNREGISTERED') !== false) {
				$this->resetInteractiveClientLogin($state);
				return $this->buildQrSetupResponse(
					$sessionId,
					$state,
					'Telegram login session expired while submitting 2FA password. Please scan the new QR code.',
				);
			}

			return [
				'status' => 'error',
				'message' => $errorMessage !== ''
					? $errorMessage
					: 'Unable to complete Telegram 2FA login. Verify your password and try again.',
			];
		}

		return $this->interactiveSetupPollLogin($sessionId, $state);
	}

	/**
	 * @param array<string, mixed> $state
	 * @return array<string, mixed>
	 */
	private function buildQrSetupResponse(string $sessionId, array $state, string $message): array {
		$clientProvider = $this->resolveInteractiveClientProvider($state);
		if ($clientProvider === null || !is_callable([$clientProvider, 'fetchLoginQrCode'])) {
			return $this->withMessageType([
				'status' => 'error',
				'message' => 'Unable to initialize Telegram Client provider for interactive setup.',
			]);
		}

		/** @var callable(): array<string, mixed> $fetchLoginQr */
		$fetchLoginQr = [$clientProvider, 'fetchLoginQrCode'];
		$qrPayload = $fetchLoginQr();
		$status = (string)($qrPayload['status'] ?? '');
		if ($status === 'done') {
			$this->interactiveSetupStateStore->delete($sessionId);
			return $this->withMessageType([
				'status' => 'done',
				'message' => 'Telegram Client session is already logged in.',
				'config' => [
					'provider' => 'telegram_client',
					'api_id' => (string)($state['api_id'] ?? ''),
					'api_hash' => (string)($state['api_hash'] ?? ''),
					'madeline_log_enabled' => (string)($state['madeline_log_enabled'] ?? '0'),
					'madeline_log_path' => (string)($state['madeline_log_path'] ?? ''),
				],
			]);
		}
		if ($status === 'needs_input') {
			$hint = trim((string)($qrPayload['hint'] ?? ''));
			return $this->withMessageType([
				'status' => 'needs_input',
				'sessionId' => $sessionId,
				'step' => 'enter_password',
				'message' => $hint !== ''
					? 'Telegram account requires 2FA password. Hint: ' . $hint
					: 'Telegram account requires 2FA password to complete login.',
				'data' => [
					'hint' => $hint,
				],
			]);
		}
		if ($status === 'error') {
			$errorMessage = trim((string)($qrPayload['message'] ?? ''));
			return $this->withMessageType([
				'status' => 'error',
				'message' => $errorMessage !== ''
					? $errorMessage
					: 'Unable to generate Telegram login QR code. Verify api_id/api_hash and try again.',
			]);
		}

		$link = trim((string)($qrPayload['link'] ?? ''));
		$qrSvg = trim((string)($qrPayload['qr_svg'] ?? ''));
		if ($link === '' || $qrSvg === '') {
			return $this->withMessageType([
				'status' => 'error',
				'message' => 'Unable to generate Telegram login QR code. Verify api_id/api_hash and try again.',
			]);
		}

		return $this->withMessageType([
			'status' => 'pending',
			'sessionId' => $sessionId,
			'step' => 'scan_qr',
			'message' => $message,
			'data' => [
				'link' => $link,
				'qr_svg' => $qrSvg,
				'expires_in' => (int)($qrPayload['expires_in'] ?? 0),
			],
		]);
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private function resolveInteractiveClientProvider(array $state): ?AProvider {
		try {
			$provider = $this->getProvider('telegram_client');
			return $provider->withRuntimeConfig([
				'provider' => 'telegram_client',
				'api_id' => (string)($state['api_id'] ?? ''),
				'api_hash' => (string)($state['api_hash'] ?? ''),
				'madeline_log_enabled' => (string)($state['madeline_log_enabled'] ?? '0'),
				'madeline_log_path' => (string)($state['madeline_log_path'] ?? ''),
			]);
		} catch (\Throwable) {
			return null;
		}
	}

	/** @param array<string, mixed> $state */
	private function resetInteractiveClientLogin(array $state): void {
		$clientProvider = $this->resolveInteractiveClientProvider($state);
		if ($clientProvider === null || !method_exists($clientProvider, 'resetLoginSession')) {
			return;
		}

		/** @var callable(): void $resetLoginSession */
		$resetLoginSession = [$clientProvider, 'resetLoginSession'];
		$resetLoginSession();
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function withMessageType(array $payload): array {
		if (!isset($payload['messageType']) && isset($payload['status'])) {
			$payload['messageType'] = match ((string)$payload['status']) {
				'done' => 'success',
				'error' => 'error',
				'needs_input', 'pending', 'cancelled' => 'info',
				default => 'info',
			};
		}

		return $payload;
	}

	/**
	 * @param array<string, string> $instanceConfig
	 * @return array<string, string>
	 */
	#[\Override]
	public function enrichTestResult(array $instanceConfig, string $identifier = ''): array {
		$providerName = trim((string)($instanceConfig['provider'] ?? ''));

		try {
			$provider = $this->getProvider($providerName);
		} catch (\Throwable) {
			return [];
		}

		if (method_exists($provider, 'enrichTestResult')) {
			/** @var callable(array<string, string>, string): array<string, string> $enricher */
			$enricher = [$provider, 'enrichTestResult'];
			return $enricher($instanceConfig, $identifier);
		}

		return [];
	}
}
