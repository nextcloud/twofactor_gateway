<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

final class SmsDouble extends AProvider {
	public const SCHEMA = [
		'id' => 'sms_double',
		'name' => 'sms_double',
		'fields' => [
			['field' => 'user',     'prompt' => 'Please enter your SMSDouble username:'],
			['field' => 'password', 'prompt' => 'Please enter your SMSDouble password:'],
			['field' => 'api_key',  'prompt' => 'Please enter your SMSDouble API key:'],
		],
	];

	public function send(string $identifier, string $message, array $extra = []): void {
	}
}

namespace OCA\TwoFactorGateway\Tests\Unit\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\SmsDouble;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class SmsDoubleTest extends TestCase {
	private function makeInMemoryAppConfig(array &$store): IAppConfig|Stub {
		$appConfig = $this->createStub(IAppConfig::class);

		$appConfig->method('getValueString')
			->willReturnCallback(function (string $appId, string $key, string $default) use (&$store) {
				$this->assertSame(Application::APP_ID, $appId);
				return array_key_exists($key, $store) ? (string)$store[$key] : $default;
			});

		$appConfig->method('setValueString')
			->willReturnCallback(function (string $appId, string $key, string $value) use (&$store) {
				$this->assertSame(Application::APP_ID, $appId);
				$store[$key] = $value;
				return true;
			});

		$appConfig->method('deleteKey')
			->willReturnCallback(function (string $appId, string $key) use (&$store) {
				$this->assertSame(Application::APP_ID, $appId);
				unset($store[$key]);
				return true;
			});

		return $appConfig;
	}

	public function testSetThenGetAndChaining(): void {
		$store = [];
		$cfg = new SmsDouble();
		$cfg->setAppConfig($this->makeInMemoryAppConfig($store));

		$this->assertSame($cfg, $cfg->setUser('alice')->setPassword('secret'));

		$this->assertSame('alice', $store['sms_double_user'] ?? null);
		$this->assertSame('secret', $store['sms_double_password'] ?? null);

		$this->assertSame('alice', $cfg->getUser());
		$this->assertSame('secret', $cfg->getPassword());
	}

	public function testDeleteKey(): void {
		$this->expectException(ConfigurationException::class);
		$store = [];
		$cfg = new SmsDouble();
		$cfg->setAppConfig($this->makeInMemoryAppConfig($store));
		$this->assertSame($cfg, $cfg->setUser('alice'));
		$this->assertSame('alice', $cfg->getUser());
		$this->assertSame($cfg, $cfg->deleteUser());
		$cfg->getUser();
	}

	public function testCamelCaseAliasConversion(): void {
		$store = [];
		$cfg = new SmsDouble();
		$cfg->setAppConfig($this->makeInMemoryAppConfig($store));

		$cfg->setApiKey('K-123');
		$this->assertSame('K-123', $store['sms_double_api_key'] ?? null);
		$this->assertSame('K-123', $cfg->getApiKey());
	}

	public function testGetMissingThrowsConfigurationException(): void {
		$this->expectException(ConfigurationException::class);

		$store = [];
		$cfg = new SmsDouble();
		$cfg->setAppConfig($this->makeInMemoryAppConfig($store));
		$cfg->getPassword();
	}

	public function testUnknownAliasThrowsConfigurationException(): void {
		$this->expectException(ConfigurationException::class);

		$store = [];
		$cfg = new SmsDouble();
		$cfg->setAppConfig($this->makeInMemoryAppConfig($store));
		$cfg->setToken('x');
	}

	public function testInvalidMethodPatternThrowsConfigurationException(): void {
		$this->expectException(ConfigurationException::class);

		$store = [];
		$cfg = new SmsDouble();
		$cfg->setAppConfig($this->makeInMemoryAppConfig($store));
		$cfg->token('x');
	}
}
