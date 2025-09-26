<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\Drivers;

use OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\AProvider;

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

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\SMS\Provider\Drivers;

use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\Drivers\SmsDouble;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;

final class SmsDoubleTest extends AppTestCase {
	public function testSetThenGetAndChaining(): void {
		$store = [];
		$cfg = new SmsDouble();
		$cfg->setAppConfig($this->makeInMemoryAppConfig($store));

		$this->assertSame($cfg, $cfg->setUser('alice')->setPassword('secret'));

		$this->assertSame('alice', $store['twofactor_gateway']['sms_double_user'] ?? null);
		$this->assertSame('secret', $store['twofactor_gateway']['sms_double_password'] ?? null);

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
		$this->assertSame('K-123', $store['twofactor_gateway']['sms_double_api_key'] ?? null);
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
