<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\BackgroundJob\GoWhatsAppSessionMonitorJob;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\BackgroundJob\GoWhatsAppSessionMonitorReconcileJob;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\Service\GoWhatsAppSessionMonitorJobManager;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GoWhatsAppSessionMonitorJobManagerTest extends TestCase {
	private GatewayFactory&MockObject $gatewayFactory;
	private IAppConfig&MockObject $appConfig;
	private IJobList&MockObject $jobList;
	private LoggerInterface&MockObject $logger;
	private AGateway&MockObject $gateway;

	private GoWhatsAppSessionMonitorJobManager $manager;

	protected function setUp(): void {
		parent::setUp();

		$this->gatewayFactory = $this->createMock(GatewayFactory::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->gateway = $this->createMock(AGateway::class);

		$this->manager = new GoWhatsAppSessionMonitorJobManager(
			gatewayFactory: $this->gatewayFactory,
			appConfig: $this->appConfig,
			jobList: $this->jobList,
			logger: $this->logger,
		);
	}

	public function testAddsJobWhenGatewayConfiguredAndJobMissing(): void {
		$this->appConfig->expects($this->once())
			->method('getValueString')
			->willReturn('0');
		$this->jobList->expects($this->exactly(2))
			->method('has')
			->willReturnMap([
				[GoWhatsAppSessionMonitorReconcileJob::class, null, false],
				[GoWhatsAppSessionMonitorJob::class, null, false],
			]);
		$this->jobList->expects($this->exactly(2))
			->method('add')
			->willReturnCallback(function (string $jobClass, mixed $argument): void {
				self::assertContains($jobClass, [
					GoWhatsAppSessionMonitorReconcileJob::class,
					GoWhatsAppSessionMonitorJob::class,
				]);
				self::assertNull($argument);
			});
		$this->logger->expects($this->exactly(2))
			->method('info')
			->with($this->logicalOr(
				'Registered GoWhatsApp monitor reconcile background job.',
				'Activated GoWhatsApp session monitor background job.',
			));

		$this->gatewayFactory->expects($this->once())
			->method('get')
			->with('gowhatsapp')
			->willReturn($this->gateway);
		$this->gateway->expects($this->once())
			->method('isComplete')
			->willReturn(true);

		$this->jobList->expects($this->never())->method('remove');

		$this->manager->sync();
	}

	public function testDoesNothingWhenGatewayConfiguredAndJobAlreadyActive(): void {
		$this->appConfig->method('getValueString')->willReturn('0');
		$this->jobList->expects($this->exactly(2))
			->method('has')
			->willReturnMap([
				[GoWhatsAppSessionMonitorReconcileJob::class, null, true],
				[GoWhatsAppSessionMonitorJob::class, null, true],
			]);
		$this->gatewayFactory->method('get')->with('gowhatsapp')->willReturn($this->gateway);
		$this->gateway->method('isComplete')->willReturn(true);

		$this->jobList->expects($this->never())->method('add');
		$this->jobList->expects($this->never())->method('remove');

		$this->manager->sync();
	}

	public function testRemovesJobWhenGatewayNotConfiguredAndJobActive(): void {
		$this->appConfig->method('getValueString')->willReturn('0');
		$this->jobList->expects($this->exactly(2))
			->method('has')
			->willReturnMap([
				[GoWhatsAppSessionMonitorReconcileJob::class, null, true],
				[GoWhatsAppSessionMonitorJob::class, null, true],
			]);
		$this->gatewayFactory->method('get')->with('gowhatsapp')->willReturn($this->gateway);
		$this->gateway->method('isComplete')->willReturn(false);

		$this->jobList->expects($this->never())->method('add');
		$this->jobList->expects($this->once())
			->method('remove')
			->with(GoWhatsAppSessionMonitorJob::class, null);

		$this->manager->sync();
	}

	public function testDoesNothingWhenGatewayNotConfiguredAndJobMissing(): void {
		$this->appConfig->method('getValueString')->willReturn('0');
		$this->jobList->expects($this->exactly(2))
			->method('has')
			->willReturnMap([
				[GoWhatsAppSessionMonitorReconcileJob::class, null, true],
				[GoWhatsAppSessionMonitorJob::class, null, false],
			]);
		$this->gatewayFactory->method('get')->with('gowhatsapp')->willReturn($this->gateway);
		$this->gateway->method('isComplete')->willReturn(false);

		$this->jobList->expects($this->never())->method('add');
		$this->jobList->expects($this->never())->method('remove');

		$this->manager->sync();
	}

	public function testDoesNotEnableSessionMonitorWhenReconfigurationIsRequired(): void {
		$this->appConfig->expects($this->once())
			->method('getValueString')
			->willReturn('1');
		$this->jobList->expects($this->exactly(2))
			->method('has')
			->willReturnMap([
				[GoWhatsAppSessionMonitorReconcileJob::class, null, true],
				[GoWhatsAppSessionMonitorJob::class, null, true],
			]);
		$this->gatewayFactory->expects($this->never())->method('get');
		$this->jobList->expects($this->never())->method('add');
		$this->jobList->expects($this->once())
			->method('remove')
			->with(GoWhatsAppSessionMonitorJob::class, null);

		$this->manager->sync();
	}

	public function testLogsAndContinuesWhenGatewayFactoryFails(): void {
		$exception = new \RuntimeException('gateway factory failure');
		$this->appConfig->expects($this->once())
			->method('getValueString')
			->willReturn('0');
		$this->jobList->expects($this->once())
			->method('has')
			->willReturnMap([
				[GoWhatsAppSessionMonitorReconcileJob::class, null, true],
				[GoWhatsAppSessionMonitorJob::class, null, false],
			]);

		$this->gatewayFactory->expects($this->once())
			->method('get')
			->with('gowhatsapp')
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

	public function testLogsAndContinuesWhenJobListFails(): void {
		$exception = new \RuntimeException('job list failure');
		$this->appConfig->expects($this->never())->method('getValueString');

		$this->jobList->expects($this->once())
			->method('has')
			->with(GoWhatsAppSessionMonitorReconcileJob::class, null)
			->willThrowException($exception);
		$this->gatewayFactory->expects($this->never())->method('get');
		$this->gateway->expects($this->never())->method('isComplete');
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
