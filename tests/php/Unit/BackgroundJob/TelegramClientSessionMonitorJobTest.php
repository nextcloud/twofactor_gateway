<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\BackgroundJob;

use OCA\TwoFactorGateway\BackgroundJob\TelegramClientSessionMonitorJob;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientSessionHealthService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TelegramClientSessionMonitorJobTest extends TestCase {
	private ClientSessionHealthService&MockObject $healthService;
	private ITimeFactory&MockObject $timeFactory;
	private TelegramClientSessionMonitorJob $job;

	protected function setUp(): void {
		parent::setUp();

		$this->healthService = $this->createMock(ClientSessionHealthService::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getTime')->willReturn(1_000_000);

		$this->job = new TelegramClientSessionMonitorJob(
			timeFactory: $this->timeFactory,
			healthService: $this->healthService,
		);
	}

	public function testRunCallsHealthServiceCheckAndDispatch(): void {
		$this->healthService->expects($this->once())->method('checkAndDispatch');

		$ref = new \ReflectionMethod(TelegramClientSessionMonitorJob::class, 'run');
		$ref->invoke($this->job, []);
	}
}
