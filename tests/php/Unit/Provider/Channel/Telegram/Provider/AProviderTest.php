<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\Telegram\Provider;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\AProvider;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AProviderTest extends TestCase {
	#[DataProvider('providerIsComplete')]
	public function testIsCompleteUsesLazyHasKeyWithoutGetKeys(array $existingKeys, bool $expected, int $expectedHasKeyCalls): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects($this->never())
			->method('getKeys');
		$appConfig->expects($this->exactly($expectedHasKeyCalls))
			->method('hasKey')
			->willReturnCallback(function (string $appId, string $key, ?bool $lazy) use ($existingKeys) {
				$this->assertSame('twofactor_gateway', $appId);
				$this->assertTrue($lazy);
				return in_array($key, $existingKeys, true);
			});

		$provider = new TelegramAProviderTestDouble();
		$provider->setAppConfig($appConfig);

		$this->assertSame($expected, $provider->isComplete());
	}

	public static function providerIsComplete(): array {
		return [
			'all_required_keys_exist' => [
				['telegram_chat_id', 'telegram_bot_token'],
				true,
				2,
			],
			'missing_last_required_key' => [
				['telegram_chat_id'],
				false,
				2,
			],
			'missing_first_required_key_short_circuit' => [
				[],
				false,
				1,
			],
		];
	}

	public function testIsCompleteReturnsTrueWhenNoFieldsConfigured(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects($this->never())
			->method('hasKey');
		$appConfig->expects($this->never())
			->method('getKeys');

		$provider = new TelegramAProviderEmptyFieldsTestDouble();
		$provider->setAppConfig($appConfig);

		$this->assertTrue($provider->isComplete());
	}
}

class TelegramAProviderTestDouble extends AProvider {
	/**
	 * @throws MessageTransmissionException
	 */
	#[\Override]
	public function send(string $identifier, string $message) {
	}

	#[\Override]
	public function createSettings(): Settings {
		return new Settings(
			name: 'Telegram Test',
			id: 'telegram',
			fields: [
				new FieldDefinition(field: 'chat_id', prompt: 'Chat id'),
				new FieldDefinition(field: 'bot_token', prompt: 'Bot token'),
			],
		);
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		return 0;
	}
}

class TelegramAProviderEmptyFieldsTestDouble extends TelegramAProviderTestDouble {
	#[\Override]
	public function createSettings(): Settings {
		return new Settings(
			name: 'Telegram Empty',
			id: 'telegram_empty',
			fields: [],
		);
	}
}
