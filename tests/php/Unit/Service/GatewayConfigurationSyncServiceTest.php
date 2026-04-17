<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Service\GatewayConfigurationSyncService;
use OCA\TwoFactorGateway\Service\GoWhatsAppSessionMonitorJobManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GatewayConfigurationSyncServiceTest extends TestCase {
	private GoWhatsAppSessionMonitorJobManager&MockObject $goWhatsAppSessionMonitorJobManager;
	private GatewayConfigurationSyncService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->goWhatsAppSessionMonitorJobManager = $this->createMock(GoWhatsAppSessionMonitorJobManager::class);
		$this->service = new GatewayConfigurationSyncService($this->goWhatsAppSessionMonitorJobManager);
	}

	public function testSyncAfterConfigurationChangeDelegatesToMonitorManager(): void {
		$this->goWhatsAppSessionMonitorJobManager->expects($this->once())
			->method('sync');

		$this->service->syncAfterConfigurationChange();
	}

	public function testSyncAfterConfigurationChangeSwallowsMonitorFailures(): void {
		$this->goWhatsAppSessionMonitorJobManager->expects($this->once())
			->method('sync')
			->willThrowException(new \RuntimeException('sync failed'));

		$this->service->syncAfterConfigurationChange();

		$this->addToAssertionCount(1);
	}
}
