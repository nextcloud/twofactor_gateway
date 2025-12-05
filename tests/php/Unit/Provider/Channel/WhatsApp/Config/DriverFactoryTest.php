<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\WhatsApp\Config;

use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Config\DriverFactory;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Drivers\CloudApiDriver;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Drivers\WebSocketDriver;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DriverFactoryTest extends TestCase {
	private DriverFactory $factory;
	private IAppConfig|MockObject $appConfig;
	private IConfig|MockObject $config;
	private IClientService|MockObject $clientService;
	private LoggerInterface|MockObject $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->config = $this->createMock(IConfig::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->factory = new DriverFactory(
			$this->appConfig,
			$this->config,
			$this->clientService,
			$this->logger,
		);
	}

	public function testCreateCloudApiDriver(): void {
		$this->appConfig->method('getValueString')
			->willReturnMap([
				['twofactor_gateway', 'whatsapp_cloud_api_key', '', 'test_key'],
				['twofactor_gateway', 'whatsapp_cloud_phone_number_id', '', 'phone_id'],
				['twofactor_gateway', 'whatsapp_cloud_business_account_id', '', 'business_id'],
				['twofactor_gateway', 'whatsapp_cloud_api_endpoint', '', ''],
				['twofactor_gateway', 'whatsapp_base_url', '', ''],
			]);

		$driver = $this->factory->create();
		$this->assertInstanceOf(CloudApiDriver::class, $driver);
	}

	public function testCreateWebSocketDriver(): void {
		$this->appConfig->method('getValueString')
			->willReturnMap([
				['twofactor_gateway', 'whatsapp_cloud_api_key', '', ''],
				['twofactor_gateway', 'whatsapp_cloud_phone_number_id', '', ''],
				['twofactor_gateway', 'whatsapp_cloud_business_account_id', '', ''],
				['twofactor_gateway', 'whatsapp_cloud_api_endpoint', '', ''],
				['twofactor_gateway', 'whatsapp_base_url', '', 'http://localhost:3000'],
			]);

		$driver = $this->factory->create();
		$this->assertInstanceOf(WebSocketDriver::class, $driver);
	}

	public function testCreateNoDriverConfigured(): void {
		$this->appConfig->method('getValueString')->willReturn('');

		$this->expectException(ConfigurationException::class);
		$this->factory->create();
	}
}
