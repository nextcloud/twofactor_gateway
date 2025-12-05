<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\WhatsApp\Drivers;

use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Drivers\CloudApiDriver;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CloudApiDriverTest extends TestCase {
	private CloudApiDriver $driver;
	private IAppConfig|MockObject $appConfig;
	private IClientService|MockObject $clientService;
	private IClient|MockObject $client;
	private LoggerInterface|MockObject $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->client = $this->createMock(IClient::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->clientService->method('newClient')->willReturn($this->client);

		$this->driver = new CloudApiDriver(
			$this->appConfig,
			$this->clientService,
			$this->logger,
		);
	}

	public function testDetectDriverWithApiKey(): void {
		$config = ['api_key' => 'test_key'];
		$result = CloudApiDriver::detectDriver($config);
		$this->assertNotNull($result);
	}

	public function testDetectDriverWithoutApiKey(): void {
		$config = ['api_key' => null];
		$result = CloudApiDriver::detectDriver($config);
		$this->assertNull($result);
	}

	public function testGetSettings(): void {
		$settings = $this->driver->getSettings();
		$this->assertEquals('WhatsApp Cloud API (Meta)', $settings->name);
		$this->assertCount(4, $settings->fields);
	}

	public function testIsConfigCompleteMissingApiKey(): void {
		$this->appConfig->method('getValueString')->willReturn('');
		$this->assertFalse($this->driver->isConfigComplete());
	}

	public function testSendWithInvalidPhoneNumber(): void {
		$this->appConfig->method('getValueString')
			->willReturnMap([
				['twofactor_gateway', 'whatsapp_cloud_phone_number_id', '', '12345'],
				['twofactor_gateway', 'whatsapp_cloud_api_key', '', 'test_key'],
				['twofactor_gateway', 'whatsapp_cloud_api_endpoint', '', ''],
			]);

		$this->expectException(MessageTransmissionException::class);
		$this->driver->send('123', 'Test message');
	}

	public function testValidateConfigWithMissingCredentials(): void {
		$this->appConfig->method('getValueString')->willReturn('');
		$this->expectException(ConfigurationException::class);
		$this->driver->validateConfig();
	}
}
