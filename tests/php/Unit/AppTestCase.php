<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\TwoFactorGateway\Tests\Unit;

use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class AppTestCase extends TestCase {

	public function replaceAppConfig(array &$store): void {
		$stub = $this->makeInMemoryAppConfig($store);
		\OC::$server->registerService(IAppConfig::class, function () use ($stub) {
			return $stub;
		});
	}

	public function makeInMemoryAppConfig(array &$store): IAppConfig|Stub {
		$appConfig = $this->createStub(IAppConfig::class);

		$appConfig->method('getValueString')
			->willReturnCallback(function (string $appId, string $key, string $default) use (&$store) {
				if (!array_key_exists($appId, $store)) {
					$store[$appId] = [];
				}
				if (array_key_exists($key, $store[$appId])) {
					return (string)$store[$appId][$key];
				}
				return $default;
			});

		$appConfig->method('setValueString')
			->willReturnCallback(function (string $appId, string $key, string $value) use (&$store) {
				$store[$appId][$key] = $value;
				return true;
			});

		$appConfig->method('deleteKey')
			->willReturnCallback(function (string $appId, string $key) use (&$store) {
				unset($store[$appId][$key]);
				return true;
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
