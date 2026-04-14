<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Controller;

use OCA\TwoFactorGateway\Controller\AdminGatewayController;
use OCA\TwoFactorGateway\Exception\GatewayInstanceNotFoundException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Service\GatewayConfigService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminGatewayControllerTest extends TestCase {
	private AdminGatewayController $controller;
	private GatewayConfigService&MockObject $configService;
	private GatewayFactory&MockObject $gatewayFactory;

	protected function setUp(): void {
		parent::setUp();
		$request = $this->createMock(IRequest::class);
		$this->configService = $this->createMock(GatewayConfigService::class);
		$this->gatewayFactory = $this->createMock(GatewayFactory::class);

		$this->controller = new AdminGatewayController(
			$request,
			$this->configService,
			$this->gatewayFactory,
		);
	}

	private function makeGatewayMock(string $id): IGateway&MockObject {
		$settings = new Settings(
			name: ucfirst($id),
			id: $id,
			fields: [new FieldDefinition('url', 'API URL')],
		);
		$mock = $this->createMock(IGateway::class);
		$mock->method('getProviderId')->willReturn($id);
		$mock->method('getSettings')->willReturn($settings);
		return $mock;
	}

	// ── listGateways ──────────────────────────────────────────────────────────

	public function testListGatewaysReturns200WithData(): void {
		$this->configService->method('getGatewayList')->willReturn([
			['id' => 'sms', 'name' => 'SMS', 'instances' => []],
		]);

		$response = $this->controller->listGateways();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertCount(1, $data);
		$this->assertSame('sms', $data[0]['id']);
	}

	// ── createInstance ────────────────────────────────────────────────────────

	public function testCreateInstanceReturns201OnSuccess(): void {
		$gateway = $this->makeGatewayMock('telegram');
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);
		$this->configService->method('createInstance')
			->with($gateway, 'Prod', ['url' => 'https://example.com'])
			->willReturn(['id' => 'abc123', 'label' => 'Prod', 'default' => true, 'createdAt' => '2026-01-01T00:00:00+00:00', 'config' => ['url' => 'https://example.com'], 'isComplete' => true]);

		$response = $this->controller->createInstance('telegram', 'Prod', ['url' => 'https://example.com']);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame('abc123', $response->getData()['id']);
	}

	public function testCreateInstanceReturns400ForUnknownGateway(): void {
		$this->gatewayFactory->method('get')->willThrowException(new \InvalidArgumentException('Invalid type'));

		$response = $this->controller->createInstance('unknown', 'Test', []);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	// ── getInstance ───────────────────────────────────────────────────────────

	public function testGetInstanceReturns200WithInstanceData(): void {
		$gateway = $this->makeGatewayMock('sms');
		$this->gatewayFactory->method('get')->with('sms')->willReturn($gateway);
		$record = ['id' => 'def456', 'label' => 'Test', 'default' => false, 'createdAt' => '2026-01-01T00:00:00+00:00', 'config' => ['url' => 'https://sms.example.com'], 'isComplete' => true];
		$this->configService->method('getInstance')->with($gateway, 'def456')->willReturn($record);

		$response = $this->controller->getInstance('sms', 'def456');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('def456', $response->getData()['id']);
	}

	public function testGetInstanceReturns404WhenNotFound(): void {
		$gateway = $this->makeGatewayMock('sms');
		$this->gatewayFactory->method('get')->with('sms')->willReturn($gateway);
		$this->configService->method('getInstance')
			->willThrowException(new GatewayInstanceNotFoundException('sms', 'notfound'));

		$response = $this->controller->getInstance('sms', 'notfound');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	// ── updateInstance ────────────────────────────────────────────────────────

	public function testUpdateInstanceReturns200OnSuccess(): void {
		$gateway = $this->makeGatewayMock('signal');
		$this->gatewayFactory->method('get')->with('signal')->willReturn($gateway);
		$record = ['id' => 'abc', 'label' => 'New', 'default' => true, 'createdAt' => '2026-01-01T00:00:00+00:00', 'config' => ['url' => 'https://signal.example.com'], 'isComplete' => true];
		$this->configService->method('updateInstance')
			->with($gateway, 'abc', 'New', ['url' => 'https://signal.example.com'])
			->willReturn($record);

		$response = $this->controller->updateInstance('signal', 'abc', 'New', ['url' => 'https://signal.example.com']);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateInstanceReturns404WhenNotFound(): void {
		$gateway = $this->makeGatewayMock('signal');
		$this->gatewayFactory->method('get')->with('signal')->willReturn($gateway);
		$this->configService->method('updateInstance')
			->willThrowException(new GatewayInstanceNotFoundException('signal', 'notfound'));

		$response = $this->controller->updateInstance('signal', 'notfound', 'X', []);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	// ── deleteInstance ────────────────────────────────────────────────────────

	public function testDeleteInstanceReturns200OnSuccess(): void {
		$gateway = $this->makeGatewayMock('xmpp');
		$this->gatewayFactory->method('get')->with('xmpp')->willReturn($gateway);
		$this->configService->expects($this->once())->method('deleteInstance')->with($gateway, 'ghi789');

		$response = $this->controller->deleteInstance('xmpp', 'ghi789');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testDeleteInstanceReturns404WhenNotFound(): void {
		$gateway = $this->makeGatewayMock('xmpp');
		$this->gatewayFactory->method('get')->with('xmpp')->willReturn($gateway);
		$this->configService->method('deleteInstance')
			->willThrowException(new GatewayInstanceNotFoundException('xmpp', 'notfound'));

		$response = $this->controller->deleteInstance('xmpp', 'notfound');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	// ── setDefaultInstance ────────────────────────────────────────────────────

	public function testSetDefaultInstanceReturns200OnSuccess(): void {
		$gateway = $this->makeGatewayMock('sms');
		$this->gatewayFactory->method('get')->with('sms')->willReturn($gateway);
		$this->configService->expects($this->once())->method('setDefaultInstance')->with($gateway, 'abc123');

		$response = $this->controller->setDefaultInstance('sms', 'abc123');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testSetDefaultInstanceReturns404WhenNotFound(): void {
		$gateway = $this->makeGatewayMock('sms');
		$this->gatewayFactory->method('get')->with('sms')->willReturn($gateway);
		$this->configService->method('setDefaultInstance')
			->willThrowException(new GatewayInstanceNotFoundException('sms', 'notfound'));

		$response = $this->controller->setDefaultInstance('sms', 'notfound');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	// ── testInstance ──────────────────────────────────────────────────────────

	public function testTestInstanceReturns200OnSuccessfulSend(): void {
		$gateway = $this->makeGatewayMock('telegram');
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);
		$record = [
			'id' => 'abc', 'label' => 'Prod', 'default' => true, 'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['url' => 'https://t.example.com'], 'isComplete' => true,
		];
		$this->configService->method('getInstance')->with($gateway, 'abc')->willReturn($record);
		$gateway->expects($this->once())->method('send')->with('+1234567890', 'Test');

		$response = $this->controller->testInstance('telegram', 'abc', '+1234567890');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['success']);
	}

	public function testTestInstanceReturns400WhenNotComplete(): void {
		$gateway = $this->makeGatewayMock('telegram');
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);
		$record = [
			'id' => 'abc', 'label' => 'Prod', 'default' => false, 'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => [], 'isComplete' => false,
		];
		$this->configService->method('getInstance')->with($gateway, 'abc')->willReturn($record);

		$response = $this->controller->testInstance('telegram', 'abc', '+1234567890');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testTestInstanceReturns404WhenNotFound(): void {
		$gateway = $this->makeGatewayMock('telegram');
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);
		$this->configService->method('getInstance')
			->willThrowException(new GatewayInstanceNotFoundException('telegram', 'nope'));

		$response = $this->controller->testInstance('telegram', 'nope', '+1234567890');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}
}
