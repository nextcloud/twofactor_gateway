<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\Signal;

use OCA\TwoFactorGateway\Provider\Channel\Signal\Gateway;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class GatewayTest extends AppTestCase {
	private IClient&MockObject $client;
	private Gateway $gateway;

	protected function setUp(): void {
		parent::setUp();

		$appConfig = $this->makeInMemoryAppConfig();
		$clientService = $this->createMock(IClientService::class);
		$this->client = $this->createMock(IClient::class);
		$clientService->method('newClient')->willReturn($this->client);

		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn(1_700_000_000);

		$logger = $this->createMock(LoggerInterface::class);

		$this->gateway = new Gateway(
			appConfig: $appConfig,
			clientService: $clientService,
			timeFactory: $timeFactory,
			logger: $logger,
		);
	}

	public function testSendUsesRecipientsArrayAndNumberForSignalCliRestApiV2(): void {
		$gateway = $this->gateway->withRuntimeConfig([
			'url' => 'http://signal.local',
			'account' => '+5511999999999',
		]);

		$postCalls = 0;
		$this->client->expects($this->exactly(2))
			->method('post')
			->willReturnCallback(function (string $url, array $options) use (&$postCalls) {
				$postCalls++;

				if ($postCalls === 1) {
					self::assertSame('http://signal.local/api/v1/rpc', $url);
					return $this->createBodyResponse('{}', 404);
				}

				self::assertSame('http://signal.local/v2/send', $url);
				self::assertSame(['+5511888777666'], $options['json']['recipients'] ?? null);
				self::assertSame('code 123', $options['json']['message'] ?? null);
				self::assertSame('+5511999999999', $options['json']['number'] ?? null);
				self::assertSame(false, $options['http_errors'] ?? null);

				return $this->createJsonResponse(['timestamp' => 123456], 201);
			});

		$this->client->expects($this->once())
			->method('get')
			->with('http://signal.local/v1/about', ['http_errors' => false])
			->willReturn($this->createJsonResponse(['versions' => ['v1', 'v2']], 200));

		$gateway->send('+5511888777666', 'code 123');
	}

	public function testSendOmitsNumberWhenAccountIsUnnecessaryForSignalCliRestApiV2(): void {
		$gateway = $this->gateway->withRuntimeConfig([
			'url' => 'http://signal.local',
			'account' => Gateway::ACCOUNT_UNNECESSARY,
		]);

		$postCalls = 0;
		$this->client->expects($this->exactly(2))
			->method('post')
			->willReturnCallback(function (string $url, array $options) use (&$postCalls) {
				$postCalls++;

				if ($postCalls === 1) {
					return $this->createBodyResponse('{}', 404);
				}

				self::assertSame('http://signal.local/v2/send', $url);
				self::assertSame(['+5511888777666'], $options['json']['recipients'] ?? null);
				self::assertArrayNotHasKey('number', $options['json']);

				return $this->createJsonResponse(['timestamp' => 123456], 201);
			});

		$this->client->expects($this->once())
			->method('get')
			->willReturn($this->createJsonResponse(['versions' => ['v2']], 200));

		$gateway->send('+5511888777666', 'code 123');
	}

	public function testSendUsesGroupIdForNativeRpcWhenIdentifierLooksLikeSignalGroup(): void {
		$groupIdentifier = base64_encode('group-identifier');
		$gateway = $this->gateway->withRuntimeConfig([
			'url' => 'http://signal.local',
			'account' => '+5511999999999',
		]);

		$postCalls = 0;
		$this->client->expects($this->exactly(2))
			->method('post')
			->willReturnCallback(function (string $url, array $options) use (&$postCalls, $groupIdentifier) {
				$postCalls++;

				if ($postCalls === 1) {
					self::assertSame('http://signal.local/api/v1/rpc', $url);
					return $this->createJsonResponse(['jsonrpc' => '2.0', 'result' => ['version' => '1.0']], 200);
				}

				self::assertSame('http://signal.local/api/v1/rpc', $url);
				self::assertSame('send', $options['json']['method'] ?? null);
				self::assertSame($groupIdentifier, $options['json']['params']['group-id'] ?? null);
				self::assertArrayNotHasKey('recipient', $options['json']['params']);
				self::assertSame('+5511999999999', $options['json']['params']['account'] ?? null);

				return $this->createJsonResponse(['jsonrpc' => '2.0', 'result' => ['timestamp' => 123456]], 200);
			});

		$gateway->send($groupIdentifier, 'group code');
	}

	public function testInteractiveSetupStartReturnsDoneWhenGatewayAlreadyHasRegisteredAccount(): void {
		$this->client->expects($this->exactly(3))
			->method('get')
			->willReturnCallback(function (string $url, array $options) {
				if ($url === 'http://signal.local/v1/about') {
					self::assertSame(['http_errors' => false], $options);
					return $this->createJsonResponse(['versions' => ['v2']], 200);
				}

				if ($url === 'http://signal.local/v1/accounts') {
					return $this->createJsonResponse(['+5511999999999'], 200);
				}

				self::assertSame('http://signal.local/v1/contacts/%2B5511999999999/%2B5511999999999', $url);
				self::assertSame('true', $options['query']['all_recipients'] ?? null);

				return $this->createJsonResponse([
					'profile_name' => 'Signal Gateway Account',
				], 200);
			});

		$response = $this->gateway->interactiveSetupStart(['url' => 'http://signal.local']);

		$this->assertSame('done', $response['status']);
		$this->assertSame('success', $response['messageType']);
		$this->assertSame('http://signal.local', $response['config']['url']);
		$this->assertSame('+5511999999999', $response['config']['account']);
		$this->assertSame('Signal Gateway Account', $response['config']['account_name']);
		$this->assertStringContainsString('already has a registered account', (string)$response['message']);
	}

	public function testInteractiveSetupStartReturnsPendingWithQrSvgWhenGatewayNeedsLinking(): void {
		$this->client->expects($this->exactly(3))
			->method('get')
			->willReturnCallback(function (string $url) {
				if ($url === 'http://signal.local/v1/about') {
					return $this->createJsonResponse(['versions' => ['v2']], 200);
				}

				if ($url === 'http://signal.local/v1/accounts') {
					return $this->createJsonResponse([], 200);
				}

				self::assertSame('http://signal.local/v1/qrcodelink/raw', $url);
				return $this->createJsonResponse([
					'device_link_uri' => 'sgnl://linkdevice?uuid=abc123',
				], 200);
			});

		$response = $this->gateway->interactiveSetupStart(['url' => 'http://signal.local']);

		$this->assertSame('pending', $response['status']);
		$this->assertSame('info', $response['messageType']);
		$this->assertSame('scan_qr', $response['step']);
		$this->assertIsString($response['sessionId']);
		$this->assertNotSame('', $response['sessionId']);
		$this->assertStringContainsString('<svg', (string)$response['data']['qr_svg']);
	}

	private function createJsonResponse(array $payload, int $status): IResponse {
		return $this->createBodyResponse(json_encode($payload, JSON_THROW_ON_ERROR), $status);
	}

	private function createBodyResponse(string $body, int $status): IResponse {
		$response = $this->createStub(IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getBody')->willReturn($body);

		return $response;
	}
}
