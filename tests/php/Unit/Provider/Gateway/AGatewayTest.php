<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Gateway;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AGatewayTest extends TestCase {
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

		$gateway = new TestGateway($appConfig);

		$this->assertSame($expected, $gateway->isComplete());
	}

	public static function providerIsComplete(): array {
		return [
			'all_required_keys_exist' => [
				['sms_api_key', 'sms_sender'],
				true,
				2,
			],
			'missing_last_required_key' => [
				['sms_api_key'],
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

	public function testIsCompleteUsesProvidedSettingsIdAndFields(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects($this->once())
			->method('hasKey')
			->willReturnCallback(function (string $appId, string $key, ?bool $lazy) {
				$this->assertSame('twofactor_gateway', $appId);
				$this->assertSame('custom_token', $key);
				$this->assertTrue($lazy);
				return true;
			});
		$appConfig->expects($this->never())
			->method('getKeys');

		$gateway = new TestGateway($appConfig);
		$settings = new Settings(
			name: 'Custom',
			id: 'custom',
			fields: [
				new FieldDefinition(field: 'token', prompt: 'Token'),
			],
		);

		$this->assertTrue($gateway->isComplete($settings));
	}

	public function testIsCompleteReturnsTrueWhenNoFieldsConfigured(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects($this->never())
			->method('hasKey');
		$appConfig->expects($this->never())
			->method('getKeys');

		$gateway = new TestGateway($appConfig);
		$settings = new Settings(
			name: 'NoFields',
			id: 'no_fields',
			fields: [],
		);

		$this->assertTrue($gateway->isComplete($settings));
	}
}

class TestGateway extends AGateway {
	/**
	 * @throws MessageTransmissionException
	 */
	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
	}

	#[\Override]
	public function createSettings(): Settings {
		return new Settings(
			name: 'SMS',
			id: 'sms',
			fields: [
				new FieldDefinition(field: 'api_key', prompt: 'API key'),
				new FieldDefinition(field: 'sender', prompt: 'Sender'),
			],
		);
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		return 0;
	}
}
