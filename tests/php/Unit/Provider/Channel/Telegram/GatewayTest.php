<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\Telegram;

use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Factory;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Gateway;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\InteractiveSetupStateStore;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\AProvider;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GatewayTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private Factory&MockObject $telegramProviderFactory;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->telegramProviderFactory = $this->createMock(Factory::class);
		TelegramGatewayProviderTestDouble::$submittedPasswords = [];
	}

	public function testProviderCatalogExposesTelegramBotAndClientImplementations(): void {
		$botProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_bot',
			name: 'Telegram Bot',
			fields: [new FieldDefinition(field: 'token', prompt: 'Token')],
		));
		$clientProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_client',
			name: 'Telegram Client API',
			fields: [new FieldDefinition(field: 'api_id', prompt: 'API ID')],
		));

		$this->telegramProviderFactory->method('getFqcnList')->willReturn([
			'BotProvider',
			'ClientProvider',
		]);
		$this->telegramProviderFactory->method('get')->willReturnMap([
			['BotProvider', $botProvider],
			['ClientProvider', $clientProvider],
		]);

		$gateway = new Gateway($this->appConfig, $this->telegramProviderFactory);
		$selector = $gateway->getProviderSelectorField();
		$catalog = $gateway->getProviderCatalog();

		$this->assertSame('provider', $selector->field);
		$this->assertSame('telegram_bot', $selector->default);
		$this->assertCount(2, $catalog);
		$this->assertSame('telegram_bot', $catalog[0]['id']);
		$this->assertSame('Telegram Bot', $catalog[0]['name']);
		$this->assertSame('telegram_client', $catalog[1]['id']);
		$this->assertSame('Telegram Client API', $catalog[1]['name']);
	}

	public function testSendUsesRuntimeConfiguredProviderWithoutPersistingGlobalSelection(): void {
		TelegramGatewayProviderTestDouble::$allSentMessages = [];
		$botProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_bot',
			name: 'Telegram Bot',
			fields: [new FieldDefinition(field: 'token', prompt: 'Token')],
		));
		$clientProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_client',
			name: 'Telegram Client API',
			fields: [new FieldDefinition(field: 'api_id', prompt: 'API ID')],
		));

		$this->telegramProviderFactory->method('get')->willReturnMap([
			['telegram_client', $clientProvider],
		]);
		$this->appConfig
			->expects($this->never())
			->method('setValueString');
		$this->appConfig
			->expects($this->never())
			->method('getValueString');

		$gateway = (new Gateway($this->appConfig, $this->telegramProviderFactory))
			->withRuntimeConfig(['provider' => 'telegram_client']);

		$gateway->send('@alice', 'code 123');

		$this->assertSame([
			['telegram_client', '@alice', 'code 123'],
		], TelegramGatewayProviderTestDouble::$allSentMessages);
	}

	public function testSendFallsBackToPersistedProviderSelectionWhenRuntimeConfigMissing(): void {
		TelegramGatewayProviderTestDouble::$allSentMessages = [];
		$botProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_bot',
			name: 'Telegram Bot',
			fields: [new FieldDefinition(field: 'token', prompt: 'Token')],
		));

		$this->telegramProviderFactory->method('get')->willReturnMap([
			['telegram_bot', $botProvider],
		]);
		$this->appConfig
			->expects($this->once())
			->method('getValueString')
			->with('twofactor_gateway', 'telegram_provider_name')
			->willReturn('telegram_bot');

		$gateway = new Gateway($this->appConfig, $this->telegramProviderFactory);
		$gateway->send('@bob', 'code 456');

		$this->assertSame([
			['telegram_bot', '@bob', 'code 456'],
		], TelegramGatewayProviderTestDouble::$allSentMessages);
	}

	public function testSendFallsBackToDefaultInstanceWhenLegacyProviderSelectionIsMissing(): void {
		TelegramGatewayProviderTestDouble::$allSentMessages = [];
		TelegramGatewayProviderTestDouble::$usedTokensByProvider = [];
		$clientProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_client',
			name: 'Telegram Client API',
			fields: [new FieldDefinition(field: 'api_id', prompt: 'API ID')],
		));

		$this->telegramProviderFactory->method('get')->willReturnMap([
			['telegram_client', $clientProvider],
		]);

		$this->appConfig
			->method('getValueString')
			->willReturnMap([
				['twofactor_gateway', 'telegram_provider_name', '', ''],
				['twofactor_gateway', 'instances:telegram', '[]', json_encode([
					['id' => 'inst1', 'label' => 'Default', 'default' => true, 'createdAt' => '2026-01-01T00:00:00+00:00'],
				], JSON_THROW_ON_ERROR)],
				['twofactor_gateway', 'telegram:inst1:provider', 'telegram_bot', 'telegram_client'],
				['twofactor_gateway', 'telegram:inst1:api_id', '', '12345'],
			]);

		$gateway = new Gateway($this->appConfig, $this->telegramProviderFactory);
		$gateway->send('@carol', 'code 999');

		$this->assertSame([
			['telegram_client', '@carol', 'code 999'],
		], TelegramGatewayProviderTestDouble::$allSentMessages);
	}

	public function testIsCompleteUsesDefaultInstanceConfigurationWhenLegacyProviderSelectionIsMissing(): void {
		$clientProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_client',
			name: 'Telegram Client API',
			fields: [new FieldDefinition(field: 'api_id', prompt: 'API ID')],
		));

		$this->telegramProviderFactory->method('get')->willReturnMap([
			['telegram_client', $clientProvider],
		]);

		$this->appConfig
			->method('getValueString')
			->willReturnMap([
				['twofactor_gateway', 'telegram_provider_name', '', ''],
				['twofactor_gateway', 'instances:telegram', '[]', json_encode([
					['id' => 'inst1', 'label' => 'Default', 'default' => true, 'createdAt' => '2026-01-01T00:00:00+00:00'],
				], JSON_THROW_ON_ERROR)],
				['twofactor_gateway', 'telegram:inst1:provider', 'telegram_bot', 'telegram_client'],
				['twofactor_gateway', 'telegram:inst1:api_id', '', '12345'],
			]);

		$gateway = new Gateway($this->appConfig, $this->telegramProviderFactory);
		$this->assertTrue($gateway->isComplete($gateway->getSettings()));
	}

	public function testCreateSettingsIncludesProviderSelectorAndSelectedProviderFields(): void {
		$clientProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_client',
			name: 'Telegram Client API',
			fields: [new FieldDefinition(field: 'api_id', prompt: 'API ID')],
		));

		$this->telegramProviderFactory->method('get')->willReturnMap([
			['telegram_client', $clientProvider],
		]);

		$gateway = (new Gateway($this->appConfig, $this->telegramProviderFactory))
			->withRuntimeConfig(['provider' => 'telegram_client']);

		$settings = $gateway->getSettings();

		$this->assertSame('Telegram', $settings->name);
		$this->assertSame('provider', $settings->fields[0]->field);
		$this->assertSame('api_id', $settings->fields[1]->field);
	}

	public function testSendPassesRuntimeTokenToProvider(): void {
		TelegramGatewayProviderTestDouble::$usedTokensByProvider = [];
		$botProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_bot',
			name: 'Telegram Bot',
			fields: [new FieldDefinition(field: 'token', prompt: 'Token')],
		));

		$this->telegramProviderFactory->method('get')->willReturnMap([
			['telegram_bot', $botProvider],
		]);

		$gateway = (new Gateway($this->appConfig, $this->telegramProviderFactory))
			->withRuntimeConfig(['provider' => 'telegram_bot', 'token' => 'abc123']);

		$gateway->send('@alice', 'code 789');

		$this->assertSame([
			['telegram_bot', 'abc123'],
		], TelegramGatewayProviderTestDouble::$usedTokensByProvider);
	}

	public function testEnrichTestResultDelegatesToSelectedProvider(): void {
		$botProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_bot',
			name: 'Telegram Bot',
			fields: [new FieldDefinition(field: 'token', prompt: 'Token')],
		));
		$botProvider->enrichmentResult = ['account_name' => 'Alice', 'account_avatar_url' => 'data:image/png;base64,Zm9v'];

		$this->telegramProviderFactory->method('get')->willReturnMap([
			['telegram_bot', $botProvider],
		]);

		$gateway = new Gateway($this->appConfig, $this->telegramProviderFactory);

		$this->assertSame(
			['account_name' => 'Alice', 'account_avatar_url' => 'data:image/png;base64,Zm9v'],
			$gateway->enrichTestResult(['provider' => 'telegram_bot', 'token' => 'abc123'], '@alice'),
		);
	}

	public function testEnrichTestResultReturnsEmptyArrayWhenProviderDoesNotSupportEnrichment(): void {
		$provider = $this->createMock(AProvider::class);

		$this->telegramProviderFactory->method('get')->willReturnMap([
			['telegram_client', $provider],
		]);

		$gateway = new Gateway($this->appConfig, $this->telegramProviderFactory);

		$this->assertSame([], $gateway->enrichTestResult(['provider' => 'telegram_client'], '@alice'));
	}

	public function testInteractiveSetupStartReturnsQrPayloadForTelegramClient(): void {
		$clientProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_client',
			name: 'Telegram Client API',
			fields: [
				new FieldDefinition(field: 'api_id', prompt: 'API ID'),
				new FieldDefinition(field: 'api_hash', prompt: 'API Hash'),
			],
		));
		$clientProvider->qrPayload = [
			'status' => 'pending',
			'link' => 'tg://login?token=abc',
			'qr_svg' => '<svg/>',
			'expires_in' => 30,
		];

		$this->telegramProviderFactory->method('get')->willReturnMap([
			['telegram_client', $clientProvider],
		]);

		$gateway = new Gateway(
			$this->appConfig,
			$this->telegramProviderFactory,
			new TelegramGatewayInteractiveSetupStateStoreTestDouble($this->appConfig),
		);
		$response = $gateway->interactiveSetupStart([
			'provider' => 'telegram_client',
			'api_id' => '12345',
			'api_hash' => 'hash',
		]);

		$this->assertSame('pending', $response['status']);
		$this->assertSame('scan_qr', $response['step']);
		$this->assertArrayHasKey('sessionId', $response);
		$this->assertSame('tg://login?token=abc', $response['data']['link']);
		$this->assertSame('<svg/>', $response['data']['qr_svg']);
	}

	public function testInteractiveSetupPollLoginReturnsDoneWhenAccountIsLoggedIn(): void {
		$clientProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_client',
			name: 'Telegram Client API',
			fields: [
				new FieldDefinition(field: 'api_id', prompt: 'API ID'),
				new FieldDefinition(field: 'api_hash', prompt: 'API Hash'),
			],
		));
		$clientProvider->qrPayload = [
			'status' => 'pending',
			'link' => 'tg://login?token=abc',
			'qr_svg' => '<svg/>',
			'expires_in' => 30,
		];
		$clientProvider->accountInfoPayload = [
			'account_name' => 'Alice Example',
			'account_avatar_url' => 'data:image/png;base64,Zm9v',
		];

		$this->telegramProviderFactory->method('get')->willReturnMap([
			['telegram_client', $clientProvider],
		]);

		$gateway = new Gateway(
			$this->appConfig,
			$this->telegramProviderFactory,
			new TelegramGatewayInteractiveSetupStateStoreTestDouble($this->appConfig),
		);
		$start = $gateway->interactiveSetupStart([
			'provider' => 'telegram_client',
			'api_id' => '12345',
			'api_hash' => 'hash',
		]);

		$sessionId = (string)($start['sessionId'] ?? '');
		$this->assertNotSame('', $sessionId);

		$response = $gateway->interactiveSetupStep($sessionId, 'poll_login');

		$this->assertSame('done', $response['status']);
		$this->assertSame('telegram_client', $response['config']['provider']);
		$this->assertSame('12345', $response['config']['api_id']);
		$this->assertSame('hash', $response['config']['api_hash']);
		$this->assertSame('Alice Example', $response['data']['account']['account_name']);
	}

	public function testInteractiveSetupCancelReturnsCancelled(): void {
		$clientProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_client',
			name: 'Telegram Client API',
			fields: [
				new FieldDefinition(field: 'api_id', prompt: 'API ID'),
				new FieldDefinition(field: 'api_hash', prompt: 'API Hash'),
			],
		));
		$clientProvider->qrPayload = [
			'status' => 'pending',
			'link' => 'tg://login?token=abc',
			'qr_svg' => '<svg/>',
			'expires_in' => 30,
		];

		$this->telegramProviderFactory->method('get')->willReturnMap([
			['telegram_client', $clientProvider],
		]);

		$gateway = new Gateway(
			$this->appConfig,
			$this->telegramProviderFactory,
			new TelegramGatewayInteractiveSetupStateStoreTestDouble($this->appConfig),
		);
		$start = $gateway->interactiveSetupStart([
			'provider' => 'telegram_client',
			'api_id' => '12345',
			'api_hash' => 'hash',
		]);

		$sessionId = (string)($start['sessionId'] ?? '');
		$response = $gateway->interactiveSetupCancel($sessionId);

		$this->assertSame('cancelled', $response['status']);
	}

	public function testInteractiveSetupPollLoginRequestsPasswordWhen2faIsRequired(): void {
		$clientProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_client',
			name: 'Telegram Client API',
			fields: [
				new FieldDefinition(field: 'api_id', prompt: 'API ID'),
				new FieldDefinition(field: 'api_hash', prompt: 'API Hash'),
			],
		));
		$clientProvider->qrPayload = [
			'status' => 'needs_input',
			'step' => 'enter_password',
			'hint' => 'birthday',
		];

		$this->telegramProviderFactory->method('get')->willReturnMap([
			['telegram_client', $clientProvider],
		]);

		$gateway = new Gateway(
			$this->appConfig,
			$this->telegramProviderFactory,
			new TelegramGatewayInteractiveSetupStateStoreTestDouble($this->appConfig),
		);

		$start = $gateway->interactiveSetupStart([
			'provider' => 'telegram_client',
			'api_id' => '12345',
			'api_hash' => 'hash',
		]);

		$this->assertSame('needs_input', $start['status']);
		$this->assertSame('enter_password', $start['step']);
		$this->assertSame('birthday', $start['data']['hint']);
	}

	public function testInteractiveSetupSubmitPasswordCompletesLogin(): void {
		$clientProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_client',
			name: 'Telegram Client API',
			fields: [
				new FieldDefinition(field: 'api_id', prompt: 'API ID'),
				new FieldDefinition(field: 'api_hash', prompt: 'API Hash'),
			],
		));
		$clientProvider->qrPayload = [
			'status' => 'needs_input',
			'step' => 'enter_password',
		];
		$clientProvider->completeTwoFactorPayload = ['status' => 'done'];
		$clientProvider->accountInfoPayload = [
			'account_name' => 'Alice Example',
			'account_avatar_url' => 'data:image/png;base64,Zm9v',
		];

		$this->telegramProviderFactory->method('get')->willReturnMap([
			['telegram_client', $clientProvider],
		]);

		$gateway = new Gateway(
			$this->appConfig,
			$this->telegramProviderFactory,
			new TelegramGatewayInteractiveSetupStateStoreTestDouble($this->appConfig),
		);

		$start = $gateway->interactiveSetupStart([
			'provider' => 'telegram_client',
			'api_id' => '12345',
			'api_hash' => 'hash',
		]);
		$sessionId = (string)($start['sessionId'] ?? '');

		$response = $gateway->interactiveSetupStep($sessionId, 'submit_password', ['password' => 'super-secret']);

		$this->assertSame('done', $response['status']);
		$this->assertContains('super-secret', TelegramGatewayProviderTestDouble::$submittedPasswords);
	}

	public function testInteractiveSetupSubmitPasswordRecoversToQrWhenAuthKeyExpired(): void {
		$clientProvider = new TelegramGatewayProviderTestDouble(new Settings(
			id: 'telegram_client',
			name: 'Telegram Client API',
			fields: [
				new FieldDefinition(field: 'api_id', prompt: 'API ID'),
				new FieldDefinition(field: 'api_hash', prompt: 'API Hash'),
			],
		));
		$clientProvider->qrPayload = [
			'status' => 'pending',
			'link' => 'tg://login?token=refresh',
			'qr_svg' => '<svg/>',
			'expires_in' => 30,
		];
		$clientProvider->completeTwoFactorPayload = [
			'status' => 'error',
			'message' => 'AUTH_KEY_UNREGISTERED',
		];

		$this->telegramProviderFactory->method('get')->willReturnMap([
			['telegram_client', $clientProvider],
		]);

		$gateway = new Gateway(
			$this->appConfig,
			$this->telegramProviderFactory,
			new TelegramGatewayInteractiveSetupStateStoreTestDouble($this->appConfig),
		);

		$start = $gateway->interactiveSetupStart([
			'provider' => 'telegram_client',
			'api_id' => '12345',
			'api_hash' => 'hash',
		]);
		$sessionId = (string)($start['sessionId'] ?? '');

		$response = $gateway->interactiveSetupStep($sessionId, 'submit_password', ['password' => 'super-secret']);

		$this->assertSame('pending', $response['status']);
		$this->assertSame('scan_qr', $response['step']);
		$this->assertSame('tg://login?token=refresh', $response['data']['link']);
	}
}

class TelegramGatewayProviderTestDouble extends AProvider {
	/** @var list<array{0: string, 1: string, 2: string}> */
	public static array $allSentMessages = [];
	/** @var list<array{0: string, 1: string}> */
	public static array $usedTokensByProvider = [];
	/** @var list<string> */
	public static array $submittedPasswords = [];
	/** @var array<string, string> */
	public array $enrichmentResult = [];
	/** @var array<string, mixed> */
	public array $qrPayload = [];
	/** @var array<string, string> */
	public array $accountInfoPayload = [];
	/** @var array<string, mixed> */
	public array $completeTwoFactorPayload = ['status' => 'done'];
	public string $lastSubmittedPassword = '';
	/** @var list<array{0: string, 1: string}> */
	public array $sentMessages = [];
	/** @var list<string> */
	public array $usedTokens = [];
	private Settings $settingsForTest;

	public function __construct(Settings $settings) {
		$this->settingsForTest = $settings;
	}

	public function createSettings(): Settings {
		return $this->settingsForTest;
	}

	/**
	 * @throws ConfigurationException
	 */
	public function send(string $identifier, string $message): void {
		$providerId = (string)($this->settingsForTest->id ?? $this->getProviderId());
		if (is_array($this->runtimeConfig) && array_key_exists('token', $this->runtimeConfig)) {
			$token = $this->getToken();
			$this->usedTokens[] = $token;
			self::$usedTokensByProvider[] = [$providerId, $token];
		}
		$this->sentMessages[] = [$identifier, $message];
		self::$allSentMessages[] = [$providerId, $identifier, $message];
	}

	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		return 0;
	}

	/**
	 * @param array<string, string> $instanceConfig
	 * @return array<string, string>
	 */
	public function enrichTestResult(array $instanceConfig, string $identifier = ''): array {
		return $this->enrichmentResult;
	}

	/** @return array<string, mixed> */
	public function fetchLoginQrCode(): array {
		return $this->qrPayload;
	}

	/** @return array<string, string> */
	public function fetchLoggedInAccountInfo(): array {
		return $this->accountInfoPayload;
	}

	/** @return array<string, mixed> */
	public function completeTwoFactorLogin(string $password): array {
		$this->lastSubmittedPassword = $password;
		self::$submittedPasswords[] = $password;
		return $this->completeTwoFactorPayload;
	}
}

class TelegramGatewayInteractiveSetupStateStoreTestDouble extends InteractiveSetupStateStore {
	/** @var array<string, array<string, mixed>> */
	private array $state = [];

	public function __construct(IAppConfig $appConfig) {
		parent::__construct($appConfig);
	}

	public function save(string $sessionId, array $state): void {
		$this->state[$sessionId] = $state;
	}

	public function load(string $sessionId): ?array {
		return $this->state[$sessionId] ?? null;
	}

	public function delete(string $sessionId): void {
		unset($this->state[$sessionId]);
	}
}
