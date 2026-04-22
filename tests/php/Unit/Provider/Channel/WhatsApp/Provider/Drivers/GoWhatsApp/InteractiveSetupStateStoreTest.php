<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\InteractiveSetupStateStore;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class InteractiveSetupStateStoreTest extends TestCase {
	/** @var array<string, array<string, string>> */
	private array $store = [];

	public function testSaveAndLoadRoundTrip(): void {
		$appConfig = $this->makeInMemoryAppConfig();
		$stateStore = new InteractiveSetupStateStore($appConfig, 1800);

		$sessionId = 'session-abc';
		$stateStore->save($sessionId, [
			'base_url' => 'https://wa.example.com',
			'username' => 'bot',
		]);

		$loaded = $stateStore->load($sessionId);
		$this->assertIsArray($loaded);
		$this->assertSame('https://wa.example.com', $loaded['base_url']);
		$this->assertSame('bot', $loaded['username']);
		$this->assertIsInt($loaded['expires_at']);
	}

	public function testLoadReturnsNullForExpiredSession(): void {
		$appConfig = $this->makeInMemoryAppConfig();
		$stateStore = new InteractiveSetupStateStore($appConfig, 0);

		$sessionId = 'session-expired';
		$stateStore->save($sessionId, ['base_url' => 'https://wa.example.com']);

		$this->assertNull($stateStore->load($sessionId));
		$this->assertFalse($appConfig->hasKey(Application::APP_ID, 'gowhatsapp_setup_state:' . $sessionId));
	}

	public function testDeleteRemovesPersistedSession(): void {
		$appConfig = $this->makeInMemoryAppConfig();
		$stateStore = new InteractiveSetupStateStore($appConfig, 1800);

		$sessionId = 'session-delete';
		$stateStore->save($sessionId, ['base_url' => 'https://wa.example.com']);
		$stateStore->delete($sessionId);

		$this->assertNull($stateStore->load($sessionId));
	}

	public function testCreateSessionIdIsHexToken(): void {
		$stateStore = new InteractiveSetupStateStore($this->makeInMemoryAppConfig(), 1800);

		$sessionId = $stateStore->createSessionId();
		$this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $sessionId);
	}

	private function makeInMemoryAppConfig(): IAppConfig|Stub {
		$appConfig = $this->createStub(IAppConfig::class);

		$appConfig->method('getValueString')
			->willReturnCallback(function (string $appId, string $key, string $default): string {
				if (isset($this->store[$appId][$key])) {
					return $this->store[$appId][$key];
				}

				return $default;
			});

		$appConfig->method('setValueString')
			->willReturnCallback(function (string $appId, string $key, string $value): bool {
				$this->store[$appId] ??= [];
				$this->store[$appId][$key] = $value;
				return true;
			});

		$appConfig->method('deleteKey')
			->willReturnCallback(function (string $appId, string $key): bool {
				unset($this->store[$appId][$key]);
				return true;
			});

		$appConfig->method('hasKey')
			->willReturnCallback(function (string $appId, string $key): bool {
				return isset($this->store[$appId][$key]);
			});

		return $appConfig;
	}
}
