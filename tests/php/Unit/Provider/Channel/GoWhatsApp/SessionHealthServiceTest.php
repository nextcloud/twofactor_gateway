<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\GoWhatsApp;

use OCA\TwoFactorGateway\Events\WhatsAppAuthenticationErrorEvent;
use OCA\TwoFactorGateway\Events\WhatsAppSessionWarningEvent;
use OCA\TwoFactorGateway\Provider\Channel\GoWhatsApp\SessionHealthService;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class SessionHealthServiceTest extends AppTestCase {
	private IAppConfig $appConfig;
	private IClient&MockObject $httpClient;
	private IClientService&MockObject $clientService;
	private IEventDispatcher&MockObject $eventDispatcher;
	private ITimeFactory&MockObject $timeFactory;
	private LoggerInterface&MockObject $logger;

	private SessionHealthService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->makeInMemoryAppConfig();

		$this->httpClient = $this->createMock(IClient::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->clientService->method('newClient')->willReturn($this->httpClient);

		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new SessionHealthService(
			appConfig: $this->appConfig,
			clientService: $this->clientService,
			eventDispatcher: $this->eventDispatcher,
			timeFactory: $this->timeFactory,
			logger: $this->logger,
		);
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – no base URL configured → logs debug, no event
	// -------------------------------------------------------------------------

	public function testSkipsCheckWhenBaseUrlNotConfigured(): void {
		$this->timeFactory->expects($this->never())->method('getTime');
		$this->httpClient->expects($this->never())->method('get');
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->checkAndDispatch();
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – healthy state → records INFO, no event dispatched
	// -------------------------------------------------------------------------

	public function testRecordsInfoEntryForHealthyDevice(): void {
		$this->configureBaseUrl('http://whatsapp.local');
		$this->configureDeviceId('dev-1');
		$now = 1_000_000;
		$this->timeFactory->method('getTime')->willReturn($now);

		$this->stubDevicesResponse([
			['id' => 'dev-1', 'state' => 'connected'],
		]);

		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->checkAndDispatch();

		$history = $this->getStoredHistory();
		$this->assertCount(1, $history);
		$this->assertSame('connected', $history[0]['state']);
		$this->assertSame($now, $history[0]['ts']);
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – single disconnect → no event yet (below threshold)
	// -------------------------------------------------------------------------

	public function testNoEventOnSingleDisconnect(): void {
		$this->configureBaseUrl('http://whatsapp.local');
		$this->configureDeviceId('dev-1');
		$this->timeFactory->method('getTime')->willReturn(1_000_000);

		$this->stubDevicesResponse([
			['id' => 'dev-1', 'state' => 'disconnected'],
		]);

		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->checkAndDispatch();
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – multiple disconnects above threshold → WARNING event
	// -------------------------------------------------------------------------

	public function testDispatchesWarningAfterRepeatedDisconnects(): void {
		$this->configureBaseUrl('http://whatsapp.local');
		$this->configureDeviceId('dev-1');
		$now = 1_000_300;
		$this->timeFactory->method('getTime')->willReturn($now);

		// Pre-populate 3 disconnects within the time window (600 s default)
		$window = 600;
		$this->storeHistory([
			['ts' => $now - $window + 10, 'state' => 'disconnected'],
			['ts' => $now - $window + 60, 'state' => 'disconnected'],
			['ts' => $now - $window + 120, 'state' => 'disconnected'],
		]);

		$this->stubDevicesResponse([
			['id' => 'dev-1', 'state' => 'disconnected'],
		]);

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
		$this->configureDeviceId('dev-1');
		$this->timeFactory->method('getTime')->willReturn(1_000_000);

		$this->stubDevicesResponse([
			['id' => 'dev-1', 'state' => 'logged_out'],
		]);

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(WhatsAppAuthenticationErrorEvent::class));

		$this->service->checkAndDispatch();
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – API unreachable → scores as disconnect, no fatal error
	// -------------------------------------------------------------------------

	public function testHandlesApiUnreachableGracefully(): void {
		$this->configureBaseUrl('http://whatsapp.local');
		$this->configureDeviceId('dev-1');
		$this->timeFactory->method('getTime')->willReturn(1_000_000);

		$this->httpClient
			->method('get')
			->willThrowException(new \Exception('Connection refused'));

		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		// Must not throw
		$this->service->checkAndDispatch();
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – old history entries are pruned (outside window)
	// -------------------------------------------------------------------------

	public function testPrunesHistoryEntriesOlderThanWindow(): void {
		$this->configureBaseUrl('http://whatsapp.local');
		$this->configureDeviceId('dev-1');
		$now = 1_000_000;
		$window = 600;
		$this->timeFactory->method('getTime')->willReturn($now);

		$this->storeHistory([
			['ts' => $now - $window - 1, 'state' => 'disconnected'],  // too old
			['ts' => $now - $window - 100, 'state' => 'disconnected'], // also too old
		]);

		$this->stubDevicesResponse([
			['id' => 'dev-1', 'state' => 'connected'],
		]);

		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->checkAndDispatch();

		// Only the fresh entry should be in history (old ones were pruned)
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
		$this->configureDeviceId('dev-1');
		$now = 1_000_300;
		$window = 600;
		$this->timeFactory->method('getTime')->willReturn($now);

		$this->storeHistory([
			['ts' => $now - $window + 10, 'state' => 'disconnected'],
			['ts' => $now - $window + 60, 'state' => 'disconnected'],
			['ts' => $now - $window + 120, 'state' => 'disconnected'],
		]);

		// Simulate that a warning was already sent 5 minutes ago
		$this->appConfig->setValueString(
			'twofactor_gateway',
			SessionHealthService::CONFIG_LAST_WARNING_TS,
			(string)($now - 5 * 60),
		);

		$this->stubDevicesResponse([
			['id' => 'dev-1', 'state' => 'disconnected'],
		]);

		// Should be suppressed during cooldown
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->checkAndDispatch();
	}

	// -------------------------------------------------------------------------
	// checkAndDispatch – WARNING fires again after cooldown expires
	// -------------------------------------------------------------------------

	public function testRepeatsWarningAfterCooldownExpires(): void {
		$this->configureBaseUrl('http://whatsapp.local');
		$this->configureDeviceId('dev-1');
		$now = 1_000_300;
		$window = 600;
		$this->timeFactory->method('getTime')->willReturn($now);

		$this->storeHistory([
			['ts' => $now - $window + 10, 'state' => 'disconnected'],
			['ts' => $now - $window + 60, 'state' => 'disconnected'],
			['ts' => $now - $window + 120, 'state' => 'disconnected'],
		]);

		// Last warning was sent 2 hours ago → cooldown (1 h default) has expired
		$this->appConfig->setValueString(
			'twofactor_gateway',
			SessionHealthService::CONFIG_LAST_WARNING_TS,
			(string)($now - 2 * 3600),
		);

		$this->stubDevicesResponse([
			['id' => 'dev-1', 'state' => 'disconnected'],
		]);

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(WhatsAppSessionWarningEvent::class));

		$this->service->checkAndDispatch();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function configureBaseUrl(string $url): void {
		$this->appConfig->setValueString('twofactor_gateway', 'go_whatsapp_base_url', $url);
	}

	private function configureDeviceId(string $deviceId): void {
		$this->appConfig->setValueString('twofactor_gateway', 'go_whatsapp_device_id', $deviceId);
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

	private function stubDevicesResponse(array $devices): void {
		$body = $this->createStub(\Psr\Http\Message\StreamInterface::class);
		$body->method('__toString')->willReturn(json_encode([
			'code' => 'SUCCESS',
			'results' => $devices,
		]));

		$response = $this->createStub(IResponse::class);
		$response->method('getBody')->willReturn($body);

		$this->httpClient->method('get')->willReturn($response);
	}
}
