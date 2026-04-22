<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Provider\Gateway\IConfigurationChangeAwareGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\GatewayConfigurationSyncService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GatewayConfigurationSyncServiceTest extends TestCase {
	private GatewayConfigurationSyncService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->service = new GatewayConfigurationSyncService();
	}

	public function testSyncAfterConfigurationChangeDelegatesToAwareGateway(): void {
		/** @var IGateway&IConfigurationChangeAwareGateway&MockObject $gateway */
		$gateway = $this->createMockForIntersectionOfInterfaces([IGateway::class, IConfigurationChangeAwareGateway::class]);
		$gateway->expects($this->once())
			->method('syncAfterConfigurationChange');

		$this->service->syncAfterConfigurationChange($gateway);
	}

	public function testSyncAfterConfigurationChangeDoesNothingForGatewayWithoutCapability(): void {
		$gateway = $this->createMock(IGateway::class);

		$this->service->syncAfterConfigurationChange($gateway);

		$this->addToAssertionCount(1);
	}

	public function testSyncAfterConfigurationChangeSwallowsAwareGatewayFailures(): void {
		/** @var IGateway&IConfigurationChangeAwareGateway&MockObject $gateway */
		$gateway = $this->createMockForIntersectionOfInterfaces([IGateway::class, IConfigurationChangeAwareGateway::class]);
		$gateway->expects($this->once())
			->method('syncAfterConfigurationChange')
			->willThrowException(new \RuntimeException('sync failed'));

		$this->service->syncAfterConfigurationChange($gateway);

		$this->addToAssertionCount(1);
	}
}
