<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 André Matthies <a.matthies@sms77.io>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IAppConfig;
use function array_intersect;

class Sms77IoConfig implements IProviderConfig {
	private const expected = [
		'sms77io_api_key',
	];

	public function __construct(
		private IAppConfig $config,
	) {
		$this->config = $config;
	}

	private function getOrFail(string $key): string {
		$val = $this->config->getValueString(Application::APP_ID, $key);
		if (empty($val)) {
			throw new ConfigurationException();
		}
		return $val;
	}

	public function getApiKey(): string {
		return $this->getOrFail('sms77io_api_key');
	}

	public function setApiKey(string $apiKey): void {
		$this->config->getValueString(Application::APP_ID, 'sms77io_api_key', $apiKey);
	}

	#[\Override]
	public function isComplete(): bool {
		$set = $this->config->getKeys(Application::APP_ID);
		return count(array_intersect($set, self::expected)) === count(self::expected);
	}

	#[\Override]
	public function remove() {
		foreach (self::expected as $key) {
			$this->config->deleteKey(Application::APP_ID, $key);
		}
	}
}
