<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\GatewayDirectIntegrationService;
use OCA\TwoFactorGateway\Service\GatewayRuntimeAvailabilityService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GatewayDirectIntegrationServiceTest extends TestCase {
	private GatewayRuntimeAvailabilityService&MockObject $gatewayRuntimeAvailabilityService;
	private GatewayDirectIntegrationService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->gatewayRuntimeAvailabilityService = $this->createMock(GatewayRuntimeAvailabilityService::class);
		$this->service = new GatewayDirectIntegrationService($this->gatewayRuntimeAvailabilityService);
	}

	public function testEnsureAvailableDelegatesToRuntimeAvailabilityService(): void {
		$gateway = $this->createMock(IGateway::class);

		$this->gatewayRuntimeAvailabilityService->expects($this->once())
			->method('getGateway')
			->with('sms')
			->willReturn($gateway);

		$this->service->ensureAvailable('sms');
	}

	public function testIsGatewayCompleteDelegatesToRuntimeAvailabilityService(): void {
		$this->gatewayRuntimeAvailabilityService->expects($this->once())
			->method('hasDirectGatewayFallback')
			->with('signal')
			->willReturn(true);

		$this->assertTrue($this->service->isGatewayComplete('signal'));
	}

	public function testSendDelegatesToResolvedGateway(): void {
		$gateway = $this->createMock(IGateway::class);
		$gateway->expects($this->once())
			->method('send')
			->with('+5511999999999', 'hello', []);

		$this->gatewayRuntimeAvailabilityService->expects($this->once())
			->method('getGateway')
			->with('whatsapp')
			->willReturn($gateway);

		$this->service->send('whatsapp', '+5511999999999', 'hello');
	}
}