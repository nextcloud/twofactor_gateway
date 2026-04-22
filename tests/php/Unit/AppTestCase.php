<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\TwoFactorGateway\Tests\Unit;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Service\GoWhatsAppSessionMonitorJobManager;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class AppTestCase extends TestCase {
	public static array $store = [];

	public function makeInMemoryAppConfig(): IAppConfig|Stub {
		self::$store = [];
		$appConfig = $this->createStub(IAppConfig::class);

		$appConfig->method('getValueString')
			->willReturnCallback(function (string $appId, string $key, string $default) {
				if (!array_key_exists($appId, self::$store)) {
					self::$store[$appId] = [];
				}
				if (array_key_exists($key, self::$store[$appId])) {
					return (string)self::$store[$appId][$key];
				}
				return $default;
			});

		$appConfig->method('setValueString')
			->willReturnCallback(function (string $appId, string $key, string $value) {
				self::$store[$appId][$key] = $value;
				return true;
			});

		$appConfig->method('deleteKey')
			->willReturnCallback(function (string $appId, string $key) {
				unset(self::$store[$appId][$key]);
				return true;
			});

		$appConfig->method('hasKey')
			->willReturnCallback(function (string $appId, string $key, ?bool $lazy = false) {
				return isset(self::$store[$appId]) && array_key_exists($key, self::$store[$appId]);
			});

		\OC::$server->registerService(IAppConfig::class, function () use ($appConfig) {
			return $appConfig;
		});

		$goWhatsAppSessionMonitorJobManager = $this->createStub(GoWhatsAppSessionMonitorJobManager::class);
		$goWhatsAppSessionMonitorJobManager->method('sync')->willReturnCallback(function () {
		});

		\OC::$server->registerService(GoWhatsAppSessionMonitorJobManager::class, function () use ($goWhatsAppSessionMonitorJobManager) {
			return $goWhatsAppSessionMonitorJobManager;
		});

		return $appConfig;
	}

	public static function createStream(array $inputs) {
		$stream = fopen('php://memory', 'r+', false);

		foreach ($inputs as $input) {
			fwrite($stream, $input . \PHP_EOL);

			if (str_contains($input, \PHP_EOL)) {
				fwrite($stream, "\x4");
			}
		}

		rewind($stream);

		return $stream;
	}
}
