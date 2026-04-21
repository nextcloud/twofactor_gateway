<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\Telegram\Provider\Drivers;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\Bot;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BotTest extends TestCase {
	private LoggerInterface&MockObject $logger;
	private IL10N&MockObject $l10n;
	private IClientService&MockObject $clientService;
	private IClient&MockObject $client;

	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(LoggerInterface::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->client = $this->createMock(IClient::class);
		$this->clientService->method('newClient')->willReturn($this->client);
	}

	public function testSendReturnsTelegramDescriptionWithoutLeakingToken(): void {
		$token = '123456:ABCDEF_SECRET_TOKEN';
		$this->client->expects($this->once())
			->method('post')
			->willThrowException(new \RuntimeException(
				'Client error: `POST https://api.telegram.org/bot' . $token . '/sendMessage` resulted in a `400 Bad Request` response: {"ok":false,"error_code":400,"description":"Bad Request: chat not found"}'
			));

		$provider = (new Bot($this->logger, $this->l10n, $this->clientService))
			->withRuntimeConfig(['token' => $token]);

		try {
			$provider->send('vitormattos', 'Test');
			$this->fail('Expected MessageTransmissionException to be thrown.');
		} catch (MessageTransmissionException $e) {
			$this->assertStringStartsWith('Failed to send Telegram message:', $e->getMessage());
			$this->assertStringContainsString('chat not found', $e->getMessage());
			$this->assertStringContainsString('numeric Telegram ID', $e->getMessage());
			$this->assertStringNotContainsString($token, $e->getMessage());
		}
	}

	public function testSendUsesGenericSafeMessageWhenDescriptionIsMissing(): void {
		$token = '123456:ABCDEF_SECRET_TOKEN';
		$this->client->expects($this->once())
			->method('post')
			->willThrowException(new \RuntimeException('Connection refused'));

		$provider = (new Bot($this->logger, $this->l10n, $this->clientService))
			->withRuntimeConfig(['token' => $token]);

		$this->expectException(MessageTransmissionException::class);
		$this->expectExceptionMessage('Failed to send Telegram message.');
		$provider->send('vitormattos', 'Test');
	}

	public function testEnrichTestResultReturnsAccountNameAndAvatarWhenAvailable(): void {
		$token = '123456:ABCDEF_SECRET_TOKEN';
		$calls = 0;
		$this->client->expects($this->exactly(3))
			->method('get')
			->willReturnCallback(function (string $url, array $options) use (&$calls, $token) {
				$calls++;
				if ($calls === 1) {
					$this->assertSame('https://api.telegram.org/bot' . $token . '/getChat', $url);
					$this->assertSame([
						'query' => ['chat_id' => '12345'],
						'timeout' => 10,
					], $options);
					return $this->createResponse('{"ok":true,"result":{"first_name":"Alice","last_name":"Cooper","photo":{"big_file_id":"file-1"}}}');
				}

				if ($calls === 2) {
					$this->assertSame('https://api.telegram.org/bot' . $token . '/getFile', $url);
					$this->assertSame([
						'query' => ['file_id' => 'file-1'],
						'timeout' => 10,
					], $options);
					return $this->createResponse('{"ok":true,"result":{"file_path":"photos/avatar.png"}}');
				}

				$this->assertSame('https://api.telegram.org/file/bot' . $token . '/photos/avatar.png', $url);
				$this->assertSame(['timeout' => 10], $options);
				return $this->createResponse('avatar-bytes');
			});

		$provider = (new Bot($this->logger, $this->l10n, $this->clientService))
			->withRuntimeConfig(['token' => $token]);

		$this->assertSame(
			[
				'account_name' => 'Alice Cooper',
				'account_avatar_url' => 'data:image/png;base64,' . base64_encode('avatar-bytes'),
			],
			$provider->enrichTestResult(['token' => $token], '12345'),
		);
	}

	public function testEnrichTestResultReturnsOnlyNameWhenChatHasNoAvatar(): void {
		$token = '123456:ABCDEF_SECRET_TOKEN';
		$this->client->expects($this->once())
			->method('get')
			->with(
				'https://api.telegram.org/bot' . $token . '/getChat',
				[
					'query' => ['chat_id' => '12345'],
					'timeout' => 10,
				],
			)
			->willReturn($this->createResponse('{"ok":true,"result":{"title":"Team Chat"}}'));

		$provider = (new Bot($this->logger, $this->l10n, $this->clientService))
			->withRuntimeConfig(['token' => $token]);

		$this->assertSame(
			['account_name' => 'Team Chat'],
			$provider->enrichTestResult(['token' => $token], '12345'),
		);
	}

	private function createResponse(string $body): IResponse&MockObject {
		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn($body);
		return $response;
	}
}
