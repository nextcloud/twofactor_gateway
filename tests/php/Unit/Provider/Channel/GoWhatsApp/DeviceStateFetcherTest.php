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
	// Device ID configured -> prefer /devices/{id}/status
	// -------------------------------------------------------------------------

	public function testUsesStatusEndpointAndReturnsLoggedInWhenAvailable(): void {
		$this->configureDeviceId('device-A');

		$this->httpClient->expects($this->once())
			->method('get')
			->with(
				'http://gowa.local/devices/device-A/status',
				$this->callback(static fn (array $opts): bool => ($opts['timeout'] ?? null) === 5),
			)
			->willReturn($this->buildStatusResponseStub(true, true));

		$this->assertSame('logged_in', $this->fetcher->fetch('http://gowa.local'));
	}

	public function testUsesStatusEndpointAndReturnsConnectedWhenLoggedInIsFalse(): void {
		$this->configureDeviceId('device-A');

		$this->httpClient->expects($this->once())
			->method('get')
			->with('http://gowa.local/devices/device-A/status', $this->anything())
			->willReturn($this->buildStatusResponseStub(true, false));

		$this->assertSame('connected', $this->fetcher->fetch('http://gowa.local'));
	}

	public function testFallsBackToDevicesListWhenStatusEndpointIndicatesDisconnected(): void {
		$this->configureDeviceId('device-A');

		$this->httpClient->expects($this->exactly(2))
			->method('get')
			->willReturnCallback(function (string $url, array $options) {
				if (str_ends_with($url, '/status')) {
					return $this->buildStatusResponseStub(false, false);
				}

				$this->assertSame('device-A', $options['headers']['X-Device-Id'] ?? '');
				return $this->buildDevicesResponseStub([
					['id' => 'device-A', 'state' => 'disconnected'],
				]);
			});

		$this->assertSame('disconnected', $this->fetcher->fetch('http://gowa.local'));
	}

	public function testFallsBackToDevicesListWhenStatusEndpointFails(): void {
		$this->configureDeviceId('device-A');

		$this->httpClient->expects($this->exactly(2))
			->method('get')
			->willReturnCallback(function (string $url, array $options) {
				if (str_ends_with($url, '/status')) {
					throw new \Exception('Status endpoint unavailable');
				}

				$this->assertSame('device-A', $options['headers']['X-Device-Id'] ?? '');
				return $this->buildDevicesResponseStub([
					['id' => 'device-A', 'state' => 'connected'],
				]);
			});

		$this->assertSame('connected', $this->fetcher->fetch('http://gowa.local'));
	}

	public function testReturnsLoggedOutWhenDeviceIdNotFoundInFallbackList(): void {
		$this->configureDeviceId('missing-id');

		$this->httpClient->expects($this->exactly(2))
			->method('get')
			->willReturnCallback(function (string $url) {
				if (str_ends_with($url, '/status')) {
					return $this->buildStatusResponseStub(false, false, 'ERROR');
				}
				return $this->buildDevicesResponseStub([
					['id' => 'device-A', 'state' => 'connected'],
				]);
			});

		$this->assertSame('logged_out', $this->fetcher->fetch('http://gowa.local'));
	}

	public function testUsesRawUrlEncodingForStatusEndpointDeviceId(): void {
		$this->configureDeviceId('my device/1');

		$this->httpClient->expects($this->once())
			->method('get')
			->with('http://gowa.local/devices/my%20device%2F1/status', $this->anything())
			->willReturn($this->buildStatusResponseStub(true, true));

		$this->assertSame('logged_in', $this->fetcher->fetch('http://gowa.local'));
	}

	// -------------------------------------------------------------------------
	// No device ID configured → use first device
	// -------------------------------------------------------------------------

	public function testReturnsFirstDeviceStateWhenNoDeviceIdConfigured(): void {
		$this->stubDevicesResponse([
			['id' => 'device-A', 'state' => 'disconnected'],
			['id' => 'device-B', 'state' => 'connected'],
		]);

		$this->assertSame('disconnected', $this->fetcher->fetch('http://gowa.local'));
	}

	public function testReturnsDisconnectedWhenDeviceListIsEmpty(): void {
		$this->stubDevicesResponse([]);

		$this->assertSame('disconnected', $this->fetcher->fetch('http://gowa.local'));
	}

	// -------------------------------------------------------------------------
	// Non-SUCCESS / malformed response
	// -------------------------------------------------------------------------

	public function testReturnsDisconnectedOnNonSuccessResponse(): void {
		$this->stubRawDevicesResponse(json_encode(['code' => 'ERROR', 'results' => []]));

		$this->assertSame('disconnected', $this->fetcher->fetch('http://gowa.local'));
	}

	public function testReturnsDisconnectedWhenResultsKeyAbsent(): void {
		$this->stubRawDevicesResponse(json_encode(['code' => 'SUCCESS']));

		$this->assertSame('disconnected', $this->fetcher->fetch('http://gowa.local'));
	}

	public function testReturnsUnreachableOnInvalidJson(): void {
		$this->stubRawDevicesResponse('not-json{{');

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

	public function testSendsXDeviceIdHeaderWhenFallbackCallsDevicesList(): void {
		$this->configureDeviceId('myDev');

		$this->httpClient->expects($this->exactly(2))
			->method('get')
			->willReturnCallback(function (string $url, array $opts) {
				if (str_ends_with($url, '/status')) {
					return $this->buildStatusResponseStub(false, false);
				}

				$this->assertSame('myDev', $opts['headers']['X-Device-Id'] ?? '');
				return $this->buildDevicesResponseStub([['id' => 'myDev', 'state' => 'connected']]);
			});

		$this->fetcher->fetch('http://gowa.local');
	}

	public function testDoesNotSendDeviceIdHeaderWhenNotConfigured(): void {
		$this->httpClient->expects($this->once())
			->method('get')
			->with(
				$this->anything(),
				$this->callback(fn (array $opts) => !isset($opts['headers'])),
			)
			->willReturn($this->buildDevicesResponseStub([]));

		$this->fetcher->fetch('http://gowa.local');
	}

	public function testUsesBaseUrlForDevicesEndpoint(): void {
		$this->httpClient->expects($this->once())
			->method('get')
			->with('http://custom.host:3000/devices', $this->anything())
			->willReturn($this->buildDevicesResponseStub([]));

		$this->fetcher->fetch('http://custom.host:3000');
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function configureDeviceId(string $deviceId): void {
		$this->appConfig->setValueString('twofactor_gateway', 'gowhatsapp_device_id', $deviceId);
	}

	private function stubDevicesResponse(array $devices): void {
		$this->httpClient->method('get')->willReturn($this->buildDevicesResponseStub($devices));
	}

	private function stubRawDevicesResponse(string $body): void {
		$stream = $this->createStub(\Psr\Http\Message\StreamInterface::class);
		$stream->method('__toString')->willReturn($body);

		$response = $this->createStub(IResponse::class);
		$response->method('getBody')->willReturn($stream);

		$this->httpClient->method('get')->willReturn($response);
	}

	private function buildDevicesResponseStub(array $devices): IResponse {
		$stream = $this->createStub(\Psr\Http\Message\StreamInterface::class);
		$stream->method('__toString')->willReturn(
			json_encode(['code' => 'SUCCESS', 'results' => $devices]),
		);

		$response = $this->createStub(IResponse::class);
		$response->method('getBody')->willReturn($stream);

		return $response;
	}

	private function buildStatusResponseStub(bool $isConnected, bool $isLoggedIn, string $code = 'SUCCESS'): IResponse {
		$stream = $this->createStub(\Psr\Http\Message\StreamInterface::class);
		$stream->method('__toString')->willReturn(
			json_encode([
				'code' => $code,
				'results' => [
					'device_id' => 'device-A',
					'is_connected' => $isConnected,
					'is_logged_in' => $isLoggedIn,
				],
			]),
		);

		$response = $this->createStub(IResponse::class);
		$response->method('getBody')->willReturn($stream);

		return $response;
	}
}
