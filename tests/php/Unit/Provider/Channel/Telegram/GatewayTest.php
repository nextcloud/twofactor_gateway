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
}

class TelegramGatewayProviderTestDouble extends AProvider {
	/** @var list<array{0: string, 1: string, 2: string}> */
	public static array $allSentMessages = [];
	/** @var list<array{0: string, 1: string}> */
	public static array $usedTokensByProvider = [];
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
}
