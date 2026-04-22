<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Provider\Channel\Telegram\BackgroundJob\TelegramClientSessionMonitorJob;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\BackgroundJob\TelegramClientSessionMonitorReconcileJob;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientSessionHealthService;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Service\TelegramClientSessionMonitorJobManager;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TelegramClientSessionMonitorJobManagerTest extends TestCase {
	private ClientSessionHealthService&MockObject $healthService;
	private IJobList&MockObject $jobList;
	private LoggerInterface&MockObject $logger;
	private TelegramClientSessionMonitorJobManager $manager;

	protected function setUp(): void {
		parent::setUp();

		$this->healthService = $this->createMock(ClientSessionHealthService::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->manager = new TelegramClientSessionMonitorJobManager(
			healthService: $this->healthService,
			jobList: $this->jobList,
			logger: $this->logger,
		);
	}

	public function testAddsJobWhenConfiguredAndMissing(): void {
		$this->healthService->expects($this->once())
			->method('isTelegramClientConfigured')
			->willReturn(true);

		$this->jobList->expects($this->exactly(2))
			->method('has')
			->willReturnMap([
				[TelegramClientSessionMonitorReconcileJob::class, null, false],
				[TelegramClientSessionMonitorJob::class, null, false],
			]);

		$this->jobList->expects($this->exactly(2))
			->method('add')
			->withAnyParameters();

		$this->jobList->expects($this->never())->method('remove');

		$this->manager->sync();
	}

	public function testRemovesJobWhenNotConfiguredAndActive(): void {
		$this->healthService->expects($this->once())
			->method('isTelegramClientConfigured')
			->willReturn(false);

		$this->jobList->expects($this->exactly(2))
			->method('has')
			->willReturnMap([
				[TelegramClientSessionMonitorReconcileJob::class, null, true],
				[TelegramClientSessionMonitorJob::class, null, true],
			]);

		$this->jobList->expects($this->never())->method('add');
		$this->jobList->expects($this->once())
			->method('remove')
			->with(TelegramClientSessionMonitorJob::class, null);

		$this->manager->sync();
	}

	public function testSwallowsExceptions(): void {
		$exception = new \RuntimeException('boom');
		$this->jobList->expects($this->once())
			->method('has')
			->with(TelegramClientSessionMonitorReconcileJob::class, null)
			->willThrowException($exception);

		$this->healthService->expects($this->never())->method('isTelegramClientConfigured');

		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'Failed to sync Telegram Client session monitor background job.',
				$this->callback(static fn (array $context): bool => ($context['exception'] ?? null) === $exception),
			);

		$this->manager->sync();
	}
}
