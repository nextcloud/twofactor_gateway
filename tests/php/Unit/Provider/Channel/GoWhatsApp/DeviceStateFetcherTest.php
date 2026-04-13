<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\GoWhatsApp;

use OCA\TwoFactorGateway\Provider\Channel\GoWhatsApp\DeviceStateFetcher;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class DeviceStateFetcherTest extends AppTestCase {
	private \OCP\IAppConfig $appConfig;
	private IClient&MockObject $httpClient;
	private IClientService&MockObject $clientService;
	private LoggerInterface&MockObject $logger;

	private DeviceStateFetcher $fetcher;

	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->makeInMemoryAppConfig();

		$this->httpClient = $this->createMock(IClient::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->clientService->method('newClient')->willReturn($this->httpClient);

		$this->logger = $this->createMock(LoggerInterface::class);

		$this->fetcher = new DeviceStateFetcher(
			appConfig: $this->appConfig,
			clientService: $this->clientService,
			logger: $this->logger,
		);
	}

	// -------------------------------------------------------------------------
	// Device ID matching
	// -------------------------------------------------------------------------

	public function testReturnsStateForMatchingDeviceId(): void {
		$this->configureDeviceId('device-A');
		$this->stubResponse([
			['id' => 'device-A', 'state' => 'connected'],
			['id' => 'device-B', 'state' => 'disconnected'],
		]);

		$this->assertSame('connected', $this->fetcher->fetch('http://gowa.local'));
	}

	public function testReturnsLoggedOutWhenDeviceIdNotFoundInList(): void {
		$this->configureDeviceId('missing-id');
		$this->stubResponse([
			['id' => 'device-A', 'state' => 'connected'],
		]);

		$this->assertSame('logged_out', $this->fetcher->fetch('http://gowa.local'));
	}

	// -------------------------------------------------------------------------
	// No device ID configured → use first device
	// -------------------------------------------------------------------------

	public function testReturnsFirstDeviceStateWhenNoDeviceIdConfigured(): void {
		$this->stubResponse([
			['id' => 'device-A', 'state' => 'disconnected'],
			['id' => 'device-B', 'state' => 'connected'],
		]);

		$this->assertSame('disconnected', $this->fetcher->fetch('http://gowa.local'));
	}

	public function testReturnsDisconnectedWhenDeviceListIsEmpty(): void {
		$this->stubResponse([]);

		$this->assertSame('disconnected', $this->fetcher->fetch('http://gowa.local'));
	}

	// -------------------------------------------------------------------------
	// Non-SUCCESS / malformed response
	// -------------------------------------------------------------------------

	public function testReturnsDisconnectedOnNonSuccessResponse(): void {
		$this->stubRawResponse(json_encode(['code' => 'ERROR', 'results' => []]));

		$this->assertSame('disconnected', $this->fetcher->fetch('http://gowa.local'));
	}

	public function testReturnsDisconnectedWhenResultsKeyAbsent(): void {
		$this->stubRawResponse(json_encode(['code' => 'SUCCESS']));

		$this->assertSame('disconnected', $this->fetcher->fetch('http://gowa.local'));
	}

	public function testReturnsUnreachableOnInvalidJson(): void {
		$this->stubRawResponse('not-json{{');

		$this->assertSame('unreachable', $this->fetcher->fetch('http://gowa.local'));
	}

	// -------------------------------------------------------------------------
	// Network failure
	// -------------------------------------------------------------------------

	public function testReturnsUnreachableOnHttpException(): void {
		$this->httpClient->method('get')->willThrowException(new \Exception('Connection refused'));

		$this->assertSame('unreachable', $this->fetcher->fetch('http://gowa.local'));
	}

	// -------------------------------------------------------------------------
	// HTTP request options
	// -------------------------------------------------------------------------

	public function testSendsXDeviceIdHeaderWhenDeviceIdIsConfigured(): void {
		$this->configureDeviceId('myDev');

		$this->httpClient->expects($this->once())
			->method('get')
			->with(
				$this->stringEndsWith('/devices'),
				$this->callback(fn (array $opts) => ($opts['headers']['X-Device-Id'] ?? '') === 'myDev'),
			)
			->willReturn($this->buildResponseStub([['id' => 'myDev', 'state' => 'connected']]));

		$this->fetcher->fetch('http://gowa.local');
	}

	public function testDoesNotSendDeviceIdHeaderWhenNotConfigured(): void {
		$this->httpClient->expects($this->once())
			->method('get')
			->with(
				$this->anything(),
				$this->callback(fn (array $opts) => !isset($opts['headers'])),
			)
			->willReturn($this->buildResponseStub([]));

		$this->fetcher->fetch('http://gowa.local');
	}

	public function testUsesBaseUrlForDevicesEndpoint(): void {
		$this->httpClient->expects($this->once())
			->method('get')
			->with('http://custom.host:3000/devices', $this->anything())
			->willReturn($this->buildResponseStub([]));

		$this->fetcher->fetch('http://custom.host:3000');
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function configureDeviceId(string $deviceId): void {
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_device_id', $deviceId);
	}

	private function stubResponse(array $devices): void {
		$this->httpClient->method('get')->willReturn($this->buildResponseStub($devices));
	}

	private function stubRawResponse(string $body): void {
		$stream = $this->createStub(\Psr\Http\Message\StreamInterface::class);
		$stream->method('__toString')->willReturn($body);

		$response = $this->createStub(IResponse::class);
		$response->method('getBody')->willReturn($stream);

		$this->httpClient->method('get')->willReturn($response);
	}

	private function buildResponseStub(array $devices): IResponse {
		$stream = $this->createStub(\Psr\Http\Message\StreamInterface::class);
		$stream->method('__toString')->willReturn(
			json_encode(['code' => 'SUCCESS', 'results' => $devices]),
		);

		$response = $this->createStub(IResponse::class);
		$response->method('getBody')->willReturn($stream);

		return $response;
	}
}
