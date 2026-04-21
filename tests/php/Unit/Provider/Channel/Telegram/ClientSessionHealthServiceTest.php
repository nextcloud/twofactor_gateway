<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\Telegram;

use OCA\TwoFactorGateway\Provider\Channel\Telegram\Events\TelegramAuthenticationErrorEvent;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientSessionHealthService;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IAppData;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class ClientSessionHealthServiceTest extends AppTestCase {
	private IAppConfig $appConfig;
	private IEventDispatcher&MockObject $eventDispatcher;
	private IL10N&MockObject $l10n;
	private IAppData&MockObject $appData;
	private IConfig&MockObject $config;
	private LoggerInterface&MockObject $logger;

	private ClientSessionHealthService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->makeInMemoryAppConfig();
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->appData = $this->createMock(IAppData::class);
		$this->config = $this->createMock(IConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new ClientSessionHealthService(
			appConfig: $this->appConfig,
			eventDispatcher: $this->eventDispatcher,
			l10n: $this->l10n,
			appData: $this->appData,
			config: $this->config,
			logger: $this->logger,
		);
	}

	public function testIsTelegramClientConfiguredReturnsFalseWhenNoActiveClientConfig(): void {
		$this->assertFalse($this->service->isTelegramClientConfigured());
	}

	public function testIsTelegramClientConfiguredReturnsTrueForLegacyClientConfig(): void {
		$this->appConfig->setValueString('twofactor_gateway', 'telegram_provider_name', 'telegram_client');
		$this->appConfig->setValueString('twofactor_gateway', 'telegram_client_api_id', '12345');
		$this->appConfig->setValueString('twofactor_gateway', 'telegram_client_api_hash', 'abc');

		$this->assertTrue($this->service->isTelegramClientConfigured());
	}

	public function testIsTelegramClientConfiguredReturnsTrueForDefaultInstanceClientConfig(): void {
		$this->appConfig->setValueString(
			'twofactor_gateway',
			'instances:telegram',
			json_encode([
				['id' => 'main', 'default' => true],
			], JSON_THROW_ON_ERROR),
		);
		$this->appConfig->setValueString('twofactor_gateway', 'telegram:main:provider', 'telegram_client');
		$this->appConfig->setValueString('twofactor_gateway', 'telegram:main:api_id', '67890');
		$this->appConfig->setValueString('twofactor_gateway', 'telegram:main:api_hash', 'def');

		$this->assertTrue($this->service->isTelegramClientConfigured());
	}

	public function testSkipsDispatchWhenNotConfigured(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->checkAndDispatch();
	}

	public function testSuppressesRepeatedErrorNotificationsWithinCooldown(): void {
		$this->appConfig->setValueString('twofactor_gateway', 'telegram_provider_name', 'telegram_client');
		$this->appConfig->setValueString('twofactor_gateway', 'telegram_client_api_id', '12345');
		$this->appConfig->setValueString('twofactor_gateway', 'telegram_client_api_hash', 'abc');
		$this->appConfig->setValueString('twofactor_gateway', 'telegram_client_health_last_error_ts', (string)(time()));
		$this->appConfig->setValueString('twofactor_gateway', 'telegram_client_health_warning_cooldown', '3600');

		// In this test, we only validate cooldown gate: no dispatch should occur.
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->service->checkAndDispatch();
	}

	public function testDispatchesTelegramAuthErrorWhenClientConfiguredButNotLoggedIn(): void {
		$this->appConfig->setValueString('twofactor_gateway', 'telegram_provider_name', 'telegram_client');
		$this->appConfig->setValueString('twofactor_gateway', 'telegram_client_api_id', '12345');
		$this->appConfig->setValueString('twofactor_gateway', 'telegram_client_api_hash', 'abc');
		$this->appConfig->setValueString('twofactor_gateway', 'telegram_client_health_warning_cooldown', '0');

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(TelegramAuthenticationErrorEvent::class));

		$this->service->checkAndDispatch();
	}
}
