<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\BackgroundJob\GoWhatsAppSessionMonitorJob;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Service\GoWhatsAppSessionMonitorJobManager;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GoWhatsAppSessionMonitorJobManagerTest extends TestCase {
	private GatewayFactory&MockObject $gatewayFactory;
	private IJobList&MockObject $jobList;
	private LoggerInterface&MockObject $logger;
	private AGateway&MockObject $gateway;

	private GoWhatsAppSessionMonitorJobManager $manager;

	protected function setUp(): void {
		parent::setUp();

		$this->gatewayFactory = $this->createMock(GatewayFactory::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->gateway = $this->createMock(AGateway::class);

		$this->manager = new GoWhatsAppSessionMonitorJobManager(
			gatewayFactory: $this->gatewayFactory,
			jobList: $this->jobList,
			logger: $this->logger,
		);
	}

	public function testAddsJobWhenGatewayConfiguredAndJobMissing(): void {
		$this->gatewayFactory->expects($this->once())
			->method('get')
			->with('gowhatsapp')
			->willReturn($this->gateway);
		$this->gateway->expects($this->once())
			->method('isComplete')
			->willReturn(true);

		$this->jobList->expects($this->once())
			->method('has')
			->with(GoWhatsAppSessionMonitorJob::class, null)
			->willReturn(false);
		$this->jobList->expects($this->once())
			->method('add')
			->with(GoWhatsAppSessionMonitorJob::class, null);
		$this->jobList->expects($this->never())->method('remove');

		$this->manager->sync();
	}

	public function testDoesNothingWhenGatewayConfiguredAndJobAlreadyActive(): void {
		$this->gatewayFactory->method('get')->with('gowhatsapp')->willReturn($this->gateway);
		$this->gateway->method('isComplete')->willReturn(true);

		$this->jobList->expects($this->once())
			->method('has')
			->with(GoWhatsAppSessionMonitorJob::class, null)
			->willReturn(true);
		$this->jobList->expects($this->never())->method('add');
		$this->jobList->expects($this->never())->method('remove');

		$this->manager->sync();
	}

	public function testRemovesJobWhenGatewayNotConfiguredAndJobActive(): void {
		$this->gatewayFactory->method('get')->with('gowhatsapp')->willReturn($this->gateway);
		$this->gateway->method('isComplete')->willReturn(false);

		$this->jobList->expects($this->once())
			->method('has')
			->with(GoWhatsAppSessionMonitorJob::class, null)
			->willReturn(true);
		$this->jobList->expects($this->never())->method('add');
		$this->jobList->expects($this->once())
			->method('remove')
			->with(GoWhatsAppSessionMonitorJob::class, null);

		$this->manager->sync();
	}

	public function testDoesNothingWhenGatewayNotConfiguredAndJobMissing(): void {
		$this->gatewayFactory->method('get')->with('gowhatsapp')->willReturn($this->gateway);
		$this->gateway->method('isComplete')->willReturn(false);

		$this->jobList->expects($this->once())
			->method('has')
			->with(GoWhatsAppSessionMonitorJob::class, null)
			->willReturn(false);
		$this->jobList->expects($this->never())->method('add');
		$this->jobList->expects($this->never())->method('remove');

		$this->manager->sync();
	}

	public function testLogsAndContinuesWhenGatewayFactoryFails(): void {
		$exception = new \RuntimeException('gateway factory failure');

		$this->gatewayFactory->expects($this->once())
			->method('get')
			->with('gowhatsapp')
			->willThrowException($exception);

		$this->jobList->expects($this->never())->method('has');
		$this->jobList->expects($this->never())->method('add');
		$this->jobList->expects($this->never())->method('remove');

		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'Failed to sync GoWhatsApp session monitor background job.',
				$this->callback(static fn (array $context): bool => ($context['exception'] ?? null) === $exception),
			);

		$this->manager->sync();
	}

	public function testLogsAndContinuesWhenJobListFails(): void {
		$exception = new \RuntimeException('job list failure');

		$this->gatewayFactory->expects($this->once())
			->method('get')
			->with('gowhatsapp')
			->willReturn($this->gateway);
		$this->gateway->expects($this->once())
			->method('isComplete')
			->willReturn(true);

		$this->jobList->expects($this->once())
			->method('has')
			->with(GoWhatsAppSessionMonitorJob::class, null)
			->willThrowException($exception);
		$this->jobList->expects($this->never())->method('add');
		$this->jobList->expects($this->never())->method('remove');

		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'Failed to sync GoWhatsApp session monitor background job.',
				$this->callback(static fn (array $context): bool => ($context['exception'] ?? null) === $exception),
			);

		$this->manager->sync();
	}
}
