<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\GoWhatsApp;

use OCA\TwoFactorGateway\Events\WhatsAppAuthenticationErrorEvent;
use OCA\TwoFactorGateway\Events\WhatsAppSessionWarningEvent;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\DeviceStateFetcher;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\HealthRiskScorer;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\SessionHealthService;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class SessionHealthServiceTest extends AppTestCase {
	private IAppConfig $appConfig;
	private DeviceStateFetcher&MockObject $fetcher;
	private HealthRiskScorer&MockObject $riskScorer;
	private IEventDispatcher&MockObject $eventDispatcher;
	private IJobList&MockObject $jobList;
	private ITimeFactory&MockObject $timeFactory;
	private LoggerInterface&MockObject $logger;

	private SessionHealthService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->makeInMemoryAppConfig();

		$this->fetcher = $this->createMock(DeviceStateFetcher::class);
		$this->riskScorer = $this->createMock(HealthRiskScorer::class);

		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new SessionHealthService(
			appConfig: $this->appConfig,
			deviceStateFetcher: $this->fetcher,
			riskScorer: $this->riskScorer,
			eventDispatcher: $this->eventDispatcher,
			jobList: $this->jobList,
			timeFactory: $this->timeFactory,
			logger: $this->logger,
		);
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – no base URL configured → logs debug, no event
	// -------------------------------------------------------------------------

	public function testSkipsCheckWhenBaseUrlNotConfigured(): void {
		$this->timeFactory->expects($this->never())->method('getTime');
		$this->fetcher->expects($this->never())->method('fetch');
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->checkAndDispatch();
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – healthy state → records entry in history, no event
	// -------------------------------------------------------------------------

	public function testRecordsInfoEntryForHealthyDevice(): void {
		$this->configureBaseUrl('http://whatsapp.local');
		$now = 1_000_000;
		$this->timeFactory->method('getTime')->willReturn($now);
		$this->fetcher->method('fetch')->willReturn('connected');
		$this->riskScorer->method('computeScore')->willReturn(0);

		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->checkAndDispatch();

		$history = $this->getStoredHistory();
		$this->assertCount(1, $history);
		$this->assertSame('connected', $history[0]['state']);
		$this->assertSame($now, $history[0]['ts']);
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – risk score below threshold → no event dispatched
	// -------------------------------------------------------------------------

	public function testNoEventWhenRiskScoreBelowThreshold(): void {
		$this->configureBaseUrl('http://whatsapp.local');
		$this->timeFactory->method('getTime')->willReturn(1_000_000);
		$this->fetcher->method('fetch')->willReturn('disconnected');
		$this->riskScorer->method('computeScore')->willReturn(10); // well below default 80

		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->checkAndDispatch();
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – risk score at or above threshold → WARNING event
	// -------------------------------------------------------------------------

	public function testDispatchesWarningWhenRiskScoreReachesThreshold(): void {
		$this->configureBaseUrl('http://whatsapp.local');
		$this->timeFactory->method('getTime')->willReturn(1_000_300);
		$this->fetcher->method('fetch')->willReturn('disconnected');
		$this->riskScorer->method('computeScore')->willReturn(90);
		$this->riskScorer->method('buildReason')->willReturn('test reason');

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(WhatsAppSessionWarningEvent::class));

		$this->service->checkAndDispatch();
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – logged_out state → CRITICAL (AuthenticationErrorEvent)
	// -------------------------------------------------------------------------

	public function testDispatchesCriticalEventForLoggedOutDevice(): void {
		$this->configureBaseUrl('http://whatsapp.local');
		$this->timeFactory->method('getTime')->willReturn(1_000_000);
		$this->fetcher->method('fetch')->willReturn('logged_out');
		$this->jobList->expects($this->once())
			->method('remove')
			->with(\OCA\TwoFactorGateway\Provider\Channel\WhatsApp\BackgroundJob\GoWhatsAppSessionMonitorJob::class, null);

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(WhatsAppAuthenticationErrorEvent::class));

		$this->service->checkAndDispatch();
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – old history entries are pruned (outside window)
	// -------------------------------------------------------------------------

	public function testPrunesHistoryEntriesOlderThanWindow(): void {
		$this->configureBaseUrl('http://whatsapp.local');
		$now = 1_000_000;
		$window = 600;
		$this->timeFactory->method('getTime')->willReturn($now);

		$this->storeHistory([
			['ts' => $now - $window - 1, 'state' => 'disconnected'],   // too old
			['ts' => $now - $window - 100, 'state' => 'disconnected'], // also too old
		]);

		$this->fetcher->method('fetch')->willReturn('connected');
		$this->riskScorer->method('computeScore')->willReturn(0);

		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->checkAndDispatch();

		$history = $this->getStoredHistory();
		foreach ($history as $entry) {
			$this->assertGreaterThan($now - $window, $entry['ts']);
		}
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – WARNING suppressed if already notified recently
	// -------------------------------------------------------------------------

	public function testDoesNotRepeatWarningWithinCooldown(): void {
		$this->configureBaseUrl('http://whatsapp.local');
		$now = 1_000_300;
		$this->timeFactory->method('getTime')->willReturn($now);
		$this->fetcher->method('fetch')->willReturn('disconnected');
		$this->riskScorer->method('computeScore')->willReturn(90);

		// Simulate that a warning was already sent 5 minutes ago
		$this->appConfig->setValueString(
			'twofactor_gateway',
			SessionHealthService::CONFIG_LAST_WARNING_TS,
			(string)($now - 5 * 60),
		);

		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->checkAndDispatch();
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – WARNING fires again after cooldown expires
	// -------------------------------------------------------------------------

	public function testRepeatsWarningAfterCooldownExpires(): void {
		$this->configureBaseUrl('http://whatsapp.local');
		$now = 1_000_300;
		$this->timeFactory->method('getTime')->willReturn($now);
		$this->fetcher->method('fetch')->willReturn('disconnected');
		$this->riskScorer->method('computeScore')->willReturn(90);
		$this->riskScorer->method('buildReason')->willReturn('reason');

		// Last warning was sent 2 hours ago → cooldown (1 h default) has expired
		$this->appConfig->setValueString(
			'twofactor_gateway',
			SessionHealthService::CONFIG_LAST_WARNING_TS,
			(string)($now - 2 * 3600),
		);

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(WhatsAppSessionWarningEvent::class));

		$this->service->checkAndDispatch();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function configureBaseUrl(string $url): void {
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_base_url', $url);
	}

	private function storeHistory(array $entries): void {
		$this->appConfig->setValueString(
			'twofactor_gateway',
			SessionHealthService::CONFIG_HISTORY_KEY,
			json_encode($entries, JSON_THROW_ON_ERROR),
		);
	}

	private function getStoredHistory(): array {
		$raw = $this->appConfig->getValueString(
			'twofactor_gateway',
			SessionHealthService::CONFIG_HISTORY_KEY,
			'[]',
		);
		return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
	}
}
