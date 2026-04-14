<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\GoWhatsApp;

use OCA\TwoFactorGateway\Provider\Channel\GoWhatsApp\SessionHealthService;
use OCA\TwoFactorGateway\Provider\Channel\GoWhatsApp\WebhookIngestionService;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class WebhookIngestionServiceTest extends AppTestCase {
	private \OCP\IAppConfig $appConfig;
	private SessionHealthService&MockObject $sessionHealthService;
	private ITimeFactory&MockObject $timeFactory;
	private LoggerInterface&MockObject $logger;

	private WebhookIngestionService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->makeInMemoryAppConfig();
		$this->sessionHealthService = $this->createMock(SessionHealthService::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new WebhookIngestionService(
			appConfig: $this->appConfig,
			sessionHealthService: $this->sessionHealthService,
			timeFactory: $this->timeFactory,
			logger: $this->logger,
		);
	}

	public function testSkipsWhenHybridWebhookIsDisabled(): void {
		$this->configureHybrid(false, 'top-secret');
		$this->timeFactory->method('getTime')->willReturn(1_000);

		$payload = ['event' => 'message', 'device_id' => 'device-A', 'payload' => ['id' => 'evt-1']];
		$raw = json_encode($payload, JSON_THROW_ON_ERROR);
		$signature = $this->buildSignature($raw, 'top-secret');

		$this->sessionHealthService->expects($this->never())->method('checkAndDispatch');

		$result = $this->service->ingest($raw, $signature);

		$this->assertFalse($result['processed']);
		$this->assertSame(202, $result['status']);
	}

	public function testRejectsWhenSignatureIsInvalid(): void {
		$this->configureHybrid(true, 'top-secret');
		$this->timeFactory->method('getTime')->willReturn(1_000);

		$payload = ['event' => 'message', 'device_id' => 'device-A', 'payload' => ['id' => 'evt-1']];
		$raw = json_encode($payload, JSON_THROW_ON_ERROR);

		$this->sessionHealthService->expects($this->never())->method('checkAndDispatch');

		$result = $this->service->ingest($raw, 'sha256=invalid');

		$this->assertFalse($result['processed']);
		$this->assertSame(401, $result['status']);
	}

	#[DataProvider('invalidSignatureProvider')]
	public function testRejectsWhenSignatureFormatIsInvalid(string $signatureHeader): void {
		$this->configureHybrid(true, 'top-secret');
		$raw = json_encode(['event' => 'message', 'device_id' => 'device-A'], JSON_THROW_ON_ERROR);

		$this->sessionHealthService->expects($this->never())->method('checkAndDispatch');

		$result = $this->service->ingest($raw, $signatureHeader);

		$this->assertFalse($result['processed']);
		$this->assertSame(401, $result['status']);
	}

	public function testProcessesValidSignedWebhookForConfiguredDevice(): void {
		$this->configureHybrid(true, 'top-secret');
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_device_id', 'device-A');
		$this->timeFactory->method('getTime')->willReturn(1_000);

		$payload = ['event' => 'message', 'device_id' => 'device-A', 'payload' => ['id' => 'evt-1']];
		$raw = json_encode($payload, JSON_THROW_ON_ERROR);
		$signature = $this->buildSignature($raw, 'top-secret');

		$this->sessionHealthService->expects($this->once())->method('checkAndDispatch');

		$result = $this->service->ingest($raw, $signature);

		$this->assertTrue($result['processed']);
		$this->assertSame(202, $result['status']);
	}

	public function testIgnoresWebhookForDifferentDeviceId(): void {
		$this->configureHybrid(true, 'top-secret');
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_device_id', 'device-A');
		$this->timeFactory->method('getTime')->willReturn(1_000);

		$payload = ['event' => 'message', 'device_id' => 'device-B', 'payload' => ['id' => 'evt-1']];
		$raw = json_encode($payload, JSON_THROW_ON_ERROR);
		$signature = $this->buildSignature($raw, 'top-secret');

		$this->sessionHealthService->expects($this->never())->method('checkAndDispatch');

		$result = $this->service->ingest($raw, $signature);

		$this->assertFalse($result['processed']);
		$this->assertSame(202, $result['status']);
	}

	public function testSuppressesDuplicateWebhookWithinReplayWindow(): void {
		$this->configureHybrid(true, 'top-secret');
		$this->timeFactory->method('getTime')->willReturnOnConsecutiveCalls(1_000, 1_010);

		$payload = ['event' => 'message', 'device_id' => 'device-A', 'payload' => ['id' => 'evt-1']];
		$raw = json_encode($payload, JSON_THROW_ON_ERROR);
		$signature = $this->buildSignature($raw, 'top-secret');

		$this->sessionHealthService->expects($this->once())->method('checkAndDispatch');

		$first = $this->service->ingest($raw, $signature);
		$second = $this->service->ingest($raw, $signature);

		$this->assertTrue($first['processed']);
		$this->assertFalse($second['processed']);
		$this->assertSame(202, $second['status']);
	}

	public function testRateLimitsHealthChecksBetweenDifferentEvents(): void {
		$this->configureHybrid(true, 'top-secret');
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_webhook_min_check_interval', '60');
		$this->timeFactory->method('getTime')->willReturnOnConsecutiveCalls(1_000, 1_030);

		$rawOne = json_encode(['event' => 'message', 'device_id' => 'device-A', 'payload' => ['id' => 'evt-1']], JSON_THROW_ON_ERROR);
		$rawTwo = json_encode(['event' => 'message', 'device_id' => 'device-A', 'payload' => ['id' => 'evt-2']], JSON_THROW_ON_ERROR);
		$signatureOne = $this->buildSignature($rawOne, 'top-secret');
		$signatureTwo = $this->buildSignature($rawTwo, 'top-secret');

		$this->sessionHealthService->expects($this->once())->method('checkAndDispatch');

		$first = $this->service->ingest($rawOne, $signatureOne);
		$second = $this->service->ingest($rawTwo, $signatureTwo);

		$this->assertTrue($first['processed']);
		$this->assertFalse($second['processed']);
		$this->assertSame(202, $second['status']);
	}

	public function testReturns503WhenSecretIsMissing(): void {
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_webhook_hybrid_enabled', '1');
		$this->timeFactory->expects($this->never())->method('getTime');

		$raw = json_encode(['event' => 'message', 'device_id' => 'device-A'], JSON_THROW_ON_ERROR);
		$signature = 'sha256=' . hash_hmac('sha256', $raw, 'top-secret');

		$this->sessionHealthService->expects($this->never())->method('checkAndDispatch');

		$result = $this->service->ingest($raw, $signature);

		$this->assertFalse($result['processed']);
		$this->assertSame(503, $result['status']);
	}

	public function testReturns400WhenPayloadIsInvalidJson(): void {
		$this->configureHybrid(true, 'top-secret');
		$this->timeFactory->expects($this->never())->method('getTime');

		$raw = '{not-json';
		$signature = $this->buildSignature($raw, 'top-secret');

		$this->sessionHealthService->expects($this->never())->method('checkAndDispatch');

		$result = $this->service->ingest($raw, $signature);

		$this->assertFalse($result['processed']);
		$this->assertSame(400, $result['status']);
	}

	public function testProcessesDuplicateEventAfterReplayWindowExpires(): void {
		$this->configureHybrid(true, 'top-secret');
		$this->timeFactory->method('getTime')->willReturnOnConsecutiveCalls(1_000, 1_121);

		$payload = ['event' => 'message', 'device_id' => 'device-A', 'payload' => ['id' => 'evt-1']];
		$raw = json_encode($payload, JSON_THROW_ON_ERROR);
		$signature = $this->buildSignature($raw, 'top-secret');

		$this->sessionHealthService->expects($this->exactly(2))->method('checkAndDispatch');

		$first = $this->service->ingest($raw, $signature);
		$second = $this->service->ingest($raw, $signature);

		$this->assertTrue($first['processed']);
		$this->assertTrue($second['processed']);
		$this->assertSame(202, $second['status']);
	}

	#[DataProvider('rateLimitBoundaryProvider')]
	public function testRateLimitBoundaryBehavior(int $deltaSeconds, bool $expectSecondProcessed): void {
		$this->configureHybrid(true, 'top-secret');
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_webhook_min_check_interval', '30');
		$this->timeFactory->method('getTime')->willReturnOnConsecutiveCalls(1_000, 1_000 + $deltaSeconds);

		$rawOne = json_encode(['event' => 'message', 'device_id' => 'device-A', 'payload' => ['id' => 'evt-1']], JSON_THROW_ON_ERROR);
		$rawTwo = json_encode(['event' => 'message', 'device_id' => 'device-A', 'payload' => ['id' => 'evt-2']], JSON_THROW_ON_ERROR);
		$signatureOne = $this->buildSignature($rawOne, 'top-secret');
		$signatureTwo = $this->buildSignature($rawTwo, 'top-secret');

		$expectedCalls = $expectSecondProcessed ? 2 : 1;
		$this->sessionHealthService->expects($this->exactly($expectedCalls))->method('checkAndDispatch');

		$first = $this->service->ingest($rawOne, $signatureOne);
		$second = $this->service->ingest($rawTwo, $signatureTwo);

		$this->assertTrue($first['processed']);
		$this->assertSame($expectSecondProcessed, $second['processed']);
	}

	public function testPersistsEventAndCheckTimestampsAfterSuccessfulProcessing(): void {
		$this->configureHybrid(true, 'top-secret');
		$this->timeFactory->method('getTime')->willReturn(1_234);

		$raw = json_encode(['event' => 'message', 'device_id' => 'device-A', 'payload' => ['id' => 'evt-7']], JSON_THROW_ON_ERROR);
		$signature = $this->buildSignature($raw, 'top-secret');

		$this->sessionHealthService->expects($this->once())->method('checkAndDispatch');

		$result = $this->service->ingest($raw, $signature);

		$this->assertTrue($result['processed']);
		$this->assertSame((string)1_234, $this->appConfig->getValueString('twofactor_gateway', 'gowhatsapp_webhook_last_event_ts', ''));
		$this->assertSame((string)1_234, $this->appConfig->getValueString('twofactor_gateway', 'gowhatsapp_webhook_last_check_ts', ''));
		$this->assertSame(hash('sha256', $raw), $this->appConfig->getValueString('twofactor_gateway', 'gowhatsapp_webhook_last_event_hash', ''));
	}

	public function testProcessesWebhookWithAllowedEventType(): void {
		$this->configureHybrid(true, 'top-secret');
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_webhook_event_filter', 'connection_status,login_success,logout_complete');
		$this->timeFactory->method('getTime')->willReturn(1_000);

		$payload = ['event_type' => 'connection_status', 'device_id' => 'device-A', 'payload' => ['id' => 'evt-1']];
		$raw = json_encode($payload, JSON_THROW_ON_ERROR);
		$signature = $this->buildSignature($raw, 'top-secret');

		$this->sessionHealthService->expects($this->once())->method('checkAndDispatch');

		$result = $this->service->ingest($raw, $signature);

		$this->assertTrue($result['processed']);
		$this->assertSame(202, $result['status']);
	}

	public function testFiltersOutWebhookWithDisallowedEventType(): void {
		$this->configureHybrid(true, 'top-secret');
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_webhook_event_filter', 'connection_status,login_success');
		$this->timeFactory->method('getTime')->willReturn(1_000);

		$payload = ['event_type' => 'message_receive', 'device_id' => 'device-A', 'payload' => ['id' => 'evt-1']];
		$raw = json_encode($payload, JSON_THROW_ON_ERROR);
		$signature = $this->buildSignature($raw, 'top-secret');

		$this->sessionHealthService->expects($this->never())->method('checkAndDispatch');

		$result = $this->service->ingest($raw, $signature);

		$this->assertFalse($result['processed']);
		$this->assertSame(202, $result['status']);
	}

	public function testAllowsWebhookWhenEventFilterIsEmpty(): void {
		$this->configureHybrid(true, 'top-secret');
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_webhook_event_filter', '');
		$this->timeFactory->method('getTime')->willReturn(1_000);

		$payload = ['event_type' => 'any_event_type', 'device_id' => 'device-A', 'payload' => ['id' => 'evt-1']];
		$raw = json_encode($payload, JSON_THROW_ON_ERROR);
		$signature = $this->buildSignature($raw, 'top-secret');

		$this->sessionHealthService->expects($this->once())->method('checkAndDispatch');

		$result = $this->service->ingest($raw, $signature);

		$this->assertTrue($result['processed']);
		$this->assertSame(202, $result['status']);
	}

	public function testAllowsWebhookWithoutEventTypeField(): void {
		$this->configureHybrid(true, 'top-secret');
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_webhook_event_filter', 'connection_status');
		$this->timeFactory->method('getTime')->willReturn(1_000);

		// Payload without event_type field (backward compatibility)
		$payload = ['device_id' => 'device-A', 'payload' => ['id' => 'evt-1']];
		$raw = json_encode($payload, JSON_THROW_ON_ERROR);
		$signature = $this->buildSignature($raw, 'top-secret');

		$this->sessionHealthService->expects($this->once())->method('checkAndDispatch');

		$result = $this->service->ingest($raw, $signature);

		$this->assertTrue($result['processed']);
		$this->assertSame(202, $result['status']);
	}

	#[DataProvider('eventFilterTrimProvider')]
	public function testEventFilterTrimsBoundaryWhitespace(string $filterConfig, string $eventType, bool $expectProcessed): void {
		$this->configureHybrid(true, 'top-secret');
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_webhook_event_filter', $filterConfig);
		$this->timeFactory->method('getTime')->willReturn(1_000);

		$payload = ['event_type' => $eventType, 'device_id' => 'device-A', 'payload' => ['id' => 'evt-1']];
		$raw = json_encode($payload, JSON_THROW_ON_ERROR);
		$signature = $this->buildSignature($raw, 'top-secret');

		$expectedCalls = $expectProcessed ? 1 : 0;
		$this->sessionHealthService->expects($this->exactly($expectedCalls))->method('checkAndDispatch');

		$result = $this->service->ingest($raw, $signature);

		$this->assertSame($expectProcessed, $result['processed']);
	}

	public static function eventFilterTrimProvider(): array {
		return [
			'whitespace in list' => ['connection_status , login_success , logout', 'login_success', true],
			'whitespace in event type' => ['connection_status,login_success', '  connection_status  ', true],
			'no match with whitespace' => [' message_receive ', 'other_event', false],
		];
	}

	public static function invalidSignatureProvider(): array {
		return [
			'wrong prefix' => ['invalid-signature'],
			'empty digest' => ['sha256='],
			'non hex digest' => ['sha256=zzzzzz'],
		];
	}

	public static function rateLimitBoundaryProvider(): array {
		return [
			'before boundary is blocked' => [29, false],
			'at boundary is allowed' => [30, true],
			'after boundary is allowed' => [31, true],
		];
	}

	private function configureHybrid(bool $enabled, string $secret): void {
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_webhook_hybrid_enabled', $enabled ? '1' : '0');
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_webhook_secret', $secret);
	}

	private function buildSignature(string $raw, string $secret): string {
		return 'sha256=' . hash_hmac('sha256', $raw, $secret);
	}
}
