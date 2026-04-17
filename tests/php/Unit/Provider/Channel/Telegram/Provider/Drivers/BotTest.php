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
			$this->assertSame('Failed to send Telegram message: chat not found. Use your numeric Telegram ID and start a conversation with the bot first.', $e->getMessage());
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
}
