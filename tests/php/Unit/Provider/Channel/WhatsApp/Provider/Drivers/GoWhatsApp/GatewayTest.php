<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\Gateway;
use OCA\TwoFactorGateway\Service\GoWhatsAppSessionMonitorJobManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GatewayTest extends TestCase {
	private IClient&MockObject $client;
	private Gateway $gateway;
	private array $store = [];

	protected function setUp(): void {
		parent::setUp();

		$appConfig = $this->makeInMemoryAppConfig();
		$clientService = $this->createMock(IClientService::class);
		$this->client = $this->createMock(IClient::class);
		$clientService->method('newClient')->willReturn($this->client);

		$l10n = $this->createMock(IL10N::class);
		$logger = $this->createMock(LoggerInterface::class);
		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$jobManager = $this->createMock(GoWhatsAppSessionMonitorJobManager::class);

		$this->gateway = new Gateway(
			appConfig: $appConfig,
			clientService: $clientService,
			l10n: $l10n,
			logger: $logger,
			eventDispatcher: $eventDispatcher,
			goWhatsAppSessionMonitorJobManager: $jobManager,
		);
	}

	public function testValidateUrlReachabilityAcceptsUnauthorizedAsReachable(): void {
		$this->setPrivate('lazyBaseUrl', 'http://gowa.local');

		$response = $this->createStub(IResponse::class);
		$response->method('getStatusCode')->willReturn(401);

		$this->client->expects($this->once())
			->method('get')
			->with('http://gowa.local/devices', ['timeout' => 5])
			->willThrowException(new class('Unauthorized', $response) extends \Exception {
				public function __construct(
					string $message,
					private IResponse $response,
				) {
					parent::__construct($message);
				}

				public function getResponse(): IResponse {
					return $this->response;
				}
			});

		$output = $this->createMock(OutputInterface::class);
		$this->assertTrue($this->invokePrivate('validateUrlReachability', [$output]));
	}

	public function testCreateNewDeviceSendsBasicAuthAndStoresDeviceId(): void {
		$this->setPrivate('lazyBaseUrl', 'http://gowa.local');
		$this->setPrivate('lazyUsername', 'api-user');
		$this->setPrivate('lazyPassword', 'api-pass');
		$this->setPrivate('lazyDeviceName', 'TwoFactor Gateway');

		$this->client->expects($this->once())
			->method('post')
			->with(
				'http://gowa.local/devices',
				$this->callback(static function (array $options): bool {
					return ($options['timeout'] ?? null) === 5
						&& ($options['auth'] ?? null) === ['api-user', 'api-pass']
						&& ($options['json']['device_id'] ?? null) === 'TwoFactor Gateway';
				}),
			)
			->willReturn($this->createJsonResponse([
				'code' => 'CREATED',
				'results' => ['id' => 'device-123'],
			]));

		$output = $this->createMock(OutputInterface::class);
		$this->assertTrue($this->invokePrivate('createNewDevice', [$output]));
		$this->assertSame('device-123', $this->getPrivate('lazyDeviceId'));
	}

	public function testFetchPairingCodeUsesBasicAuthAndDeviceHeader(): void {
		$this->setPrivate('lazyBaseUrl', 'http://gowa.local');
		$this->setPrivate('lazyPhone', '5511999999999');
		$this->setPrivate('lazyUsername', 'api-user');
		$this->setPrivate('lazyPassword', 'api-pass');
		$this->setPrivate('lazyDeviceId', 'device-123');

		$this->client->expects($this->once())
			->method('get')
			->with(
				'http://gowa.local/app/login-with-code',
				$this->callback(static function (array $options): bool {
					return ($options['query']['phone'] ?? null) === '5511999999999'
						&& ($options['auth'] ?? null) === ['api-user', 'api-pass']
						&& ($options['headers']['X-Device-Id'] ?? null) === 'device-123';
				}),
			)
			->willReturn($this->createJsonResponse([
				'code' => 'SUCCESS',
				'results' => ['pair_code' => 'ABC123'],
			]));

		$this->assertSame(
			['success' => true, 'code' => 'ABC123', 'alreadyLoggedIn' => false, 'errorMessage' => ''],
			$this->invokePrivate('fetchPairingCode'),
		);
	}

	public function testEnrichTestResultUsesTestIdentifierForLookup(): void {
		$instanceConfig = [
			'base_url' => 'http://gowa.local',
			'username' => 'api-user',
			'password' => 'api-pass',
			'phone' => '5511999999999',
			'device_id' => 'device-123',
		];

		$callIndex = 0;
		$this->client->expects($this->exactly(3))
			->method('get')
			->willReturnCallback(function (string $url, array $options) use (&$callIndex) {
				$callIndex++;

				if ($callIndex === 1) {
					self::assertSame('http://gowa.local/user/info', $url);
					self::assertSame('552120422073@s.whatsapp.net', $options['query']['phone'] ?? null);
					self::assertSame(['api-user', 'api-pass'], $options['auth'] ?? null);
					self::assertSame('device-123', $options['headers']['X-Device-Id'] ?? null);
					self::assertSame(5, $options['timeout'] ?? null);

					return $this->createJsonResponse([
						'code' => 'SUCCESS',
						'results' => [
							'data' => [[
								'name' => '552120422073',
								'verified_name' => '',
							]],
						],
					]);
				}

				if ($callIndex === 2) {
					self::assertSame('http://gowa.local/user/avatar', $url);
					self::assertSame('552120422073@s.whatsapp.net', $options['query']['phone'] ?? null);
					self::assertSame('true', $options['query']['is_preview'] ?? null);
					self::assertSame(['api-user', 'api-pass'], $options['auth'] ?? null);
					self::assertSame('device-123', $options['headers']['X-Device-Id'] ?? null);
					self::assertSame(5, $options['timeout'] ?? null);

					return $this->createJsonResponse([
						'code' => 'SUCCESS',
						'results' => [
							'url' => 'https://wa.example/avatar.png',
						],
					]);
				}

				self::assertSame('https://wa.example/avatar.png', $url);
				self::assertSame(['timeout' => 5], $options);

				return $this->createBodyResponse('avatar-bytes');
			});

		$this->assertSame(
			['account_name' => '552120422073', 'account_avatar_url' => 'data:image/png;base64,' . base64_encode('avatar-bytes')],
			$this->gateway->enrichTestResult($instanceConfig, '552120422073'),
		);
	}

	public function testEnrichTestResultFallsBackToInstancePhoneWhenIdentifierIsMissing(): void {
		$instanceConfig = [
			'base_url' => 'http://gowa.local',
			'phone' => '5511999999999',
		];

		$callIndex = 0;
		$this->client->expects($this->exactly(3))
			->method('get')
			->willReturnCallback(function (string $url, array $options) use (&$callIndex) {
				$callIndex++;

				if ($callIndex === 1) {
					self::assertSame('http://gowa.local/user/info', $url);
					self::assertSame('5511999999999@s.whatsapp.net', $options['query']['phone'] ?? null);
					self::assertSame(5, $options['timeout'] ?? null);

					return $this->createJsonResponse([
						'code' => 'SUCCESS',
						'results' => [
							'data' => [[
								'name' => 'Configured Account',
								'verified_name' => '',
							]],
						],
					]);
				}

				if ($callIndex === 2) {
					self::assertSame('http://gowa.local/user/avatar', $url);
					self::assertSame('5511999999999@s.whatsapp.net', $options['query']['phone'] ?? null);
					self::assertSame('true', $options['query']['is_preview'] ?? null);
					self::assertSame(5, $options['timeout'] ?? null);

					return $this->createJsonResponse([
						'code' => 'SUCCESS',
						'results' => [
							'url' => 'https://wa.example/configured-avatar.png',
						],
					]);
				}

				self::assertSame('https://wa.example/configured-avatar.png', $url);
				self::assertSame(['timeout' => 5], $options);

				return $this->createBodyResponse('configured-avatar-bytes');
			});

		$this->assertSame(
			['account_name' => 'Configured Account', 'account_avatar_url' => 'data:image/png;base64,' . base64_encode('configured-avatar-bytes')],
			$this->gateway->enrichTestResult($instanceConfig),
		);
	}

	private function createJsonResponse(array $payload): IResponse {
		$stream = $this->createStub(StreamInterface::class);
		$stream->method('__toString')->willReturn((string)json_encode($payload));

		$response = $this->createStub(IResponse::class);
		$response->method('getBody')->willReturn($stream);

		return $response;
	}

	private function createBodyResponse(string $body): IResponse {
		$stream = $this->createStub(StreamInterface::class);
		$stream->method('__toString')->willReturn($body);

		$response = $this->createStub(IResponse::class);
		$response->method('getBody')->willReturn($stream);

		return $response;
	}

	private function invokePrivate(string $method, array $args = []): mixed {
		$invoker = \Closure::bind(
			function (string $methodName, array $arguments): mixed {
				return $this->{$methodName}(...$arguments);
			},
			$this->gateway,
			$this->gateway,
		);

		return $invoker($method, $args);
	}

	private function setPrivate(string $property, mixed $value): void {
		$setter = \Closure::bind(
			function (string $propertyName, mixed $propertyValue): void {
				$this->{$propertyName} = $propertyValue;
			},
			$this->gateway,
			$this->gateway,
		);

		$setter($property, $value);
	}

	private function getPrivate(string $property): mixed {
		$getter = \Closure::bind(
			function (string $propertyName): mixed {
				return $this->{$propertyName};
			},
			$this->gateway,
			$this->gateway,
		);

		return $getter($property);
	}

	private function makeInMemoryAppConfig(): IAppConfig|Stub {
		$appConfig = $this->createStub(IAppConfig::class);

		$appConfig->method('getValueString')
			->willReturnCallback(function (string $appId, string $key, string $default) {
				if (!array_key_exists($appId, $this->store)) {
					$this->store[$appId] = [];
				}

				if (array_key_exists($key, $this->store[$appId])) {
					return (string)$this->store[$appId][$key];
				}

				return $default;
			});

		$appConfig->method('setValueString')
			->willReturnCallback(function (string $appId, string $key, string $value) {
				if (!array_key_exists($appId, $this->store)) {
					$this->store[$appId] = [];
				}

				$this->store[$appId][$key] = $value;
				return true;
			});

		return $appConfig;
	}
}
