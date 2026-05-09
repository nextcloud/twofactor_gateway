<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 seven communications GmbH & Co. KG <support@seven.io>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\SMS\Provider\Drivers;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\Drivers\Sms77Io;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\MockObject\MockObject;

final class Sms77IoTest extends AppTestCase {
	private const ENDPOINT = 'https://gateway.seven.io/api/sms';

	private IClientService&MockObject $clientService;
	private IClient&MockObject $client;

	protected function setUp(): void {
		parent::setUp();
		$this->client = $this->createMock(IClient::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->clientService->method('newClient')->willReturn($this->client);
	}

	public function testSettingsKeepsLegacyIdAndUsesSevenBranding(): void {
		$settings = (new Sms77Io($this->clientService))->createSettings();

		$this->assertSame('sms77io', $settings->id);
		$this->assertSame('seven (formerly sms77.io)', $settings->name);
		$this->assertCount(1, $settings->fields);
		$this->assertSame('api_key', $settings->fields[0]->field);
	}

	public function testSendPostsToSevenWithApiKeyHeaderAndParams(): void {
		$driver = $this->makeConfiguredDriver('secret-key');

		$this->client->expects($this->once())
			->method('post')
			->with(
				self::ENDPOINT,
				$this->callback(function (array $opts): bool {
					$this->assertSame('secret-key', $opts['headers']['X-Api-Key']);
					$this->assertSame('application/json', $opts['headers']['Accept']);
					$this->assertSame('+4915123456789', $opts['body']['to']);
					$this->assertSame('hello', $opts['body']['text']);
					$this->assertSame('nextcloud', $opts['body']['sendWith']);
					$this->assertArrayNotHasKey('json', $opts['body']);
					return true;
				}),
			)
			->willReturn($this->makeResponse('{"success":"100","messages":[{"id":"abc"}]}'));

		$driver->send('+4915123456789', 'hello');
	}

	public function testSendThrowsWhenGatewayReportsFailureCode(): void {
		$driver = $this->makeConfiguredDriver('secret-key');
		$this->client->method('post')->willReturn(
			$this->makeResponse('{"success":"900","messages":[]}'),
		);

		$this->expectException(MessageTransmissionException::class);
		$this->expectExceptionMessage('seven SMS gateway returned status 900');
		$driver->send('+4915123456789', 'hello');
	}

	public function testSendThrowsWhenPerRecipientDeliveryFails(): void {
		// seven returns top-level success=100 (request accepted) but flags the
		// individual recipient as failed. The driver must surface that as an
		// error, otherwise an invalid phone number silently passes.
		$driver = $this->makeConfiguredDriver('secret-key');
		$this->client->method('post')->willReturn(
			$this->makeResponse('{"success":"100","messages":[{"success":false,"error":202,"error_text":"recipient invalid"}]}'),
		);

		$this->expectException(MessageTransmissionException::class);
		$this->expectExceptionMessage('seven SMS gateway rejected recipient: recipient invalid');
		$driver->send('+4915123456789', 'hello');
	}

	public function testSendAcceptsPlainTextSuccessFallback(): void {
		$driver = $this->makeConfiguredDriver('secret-key');
		$this->client->method('post')->willReturn(
			$this->makeResponse("100\n12345"),
		);

		$driver->send('+4915123456789', 'hello');
		$this->expectNotToPerformAssertions();
	}

	public function testSendThrowsWhenHttpClientFails(): void {
		$driver = $this->makeConfiguredDriver('secret-key');
		$this->client->method('post')->willThrowException(new \RuntimeException('connection refused'));

		try {
			$driver->send('+4915123456789', 'hello');
			$this->fail('Expected MessageTransmissionException');
		} catch (MessageTransmissionException $e) {
			$this->assertStringContainsString('seven SMS gateway', $e->getMessage());
			$this->assertStringContainsString('connection refused', $e->getMessage());
			$this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
		}
	}

	private function makeConfiguredDriver(string $apiKey): Sms77Io {
		$driver = new Sms77Io($this->clientService);
		$driver->setAppConfig($this->makeInMemoryAppConfig());
		$driver->setApiKey($apiKey);
		return $driver;
	}

	private function makeResponse(string $body): IResponse&MockObject {
		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn($body);
		return $response;
	}
}
