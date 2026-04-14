<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\BackgroundJob;

use OCA\TwoFactorGateway\BackgroundJob\GoWhatsAppSessionMonitorJob;
use OCA\TwoFactorGateway\Provider\Channel\GoWhatsApp\SessionHealthService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GoWhatsAppSessionMonitorJobTest extends TestCase {
	private SessionHealthService&MockObject $healthService;
	private ITimeFactory&MockObject $timeFactory;
	private GoWhatsAppSessionMonitorJob $job;

	protected function setUp(): void {
		parent::setUp();

		$this->healthService = $this->createMock(SessionHealthService::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getTime')->willReturn(1_000_000);

		$this->job = new GoWhatsAppSessionMonitorJob(
			timeFactory: $this->timeFactory,
			healthService: $this->healthService,
		);
	}

	public function testRunCallsHealthServiceCheckAndDispatch(): void {
		$this->healthService->expects($this->once())->method('checkAndDispatch');

		// Use reflection to call the protected run() method
		$ref = new \ReflectionMethod(GoWhatsAppSessionMonitorJob::class, 'run');
		$ref->invoke($this->job, []);
	}

	public function testJobExtendsTimedJobBase(): void {
		$this->assertInstanceOf(\OCP\BackgroundJob\TimedJob::class, $this->job);
	}
}
