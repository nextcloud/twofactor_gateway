<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Marcin Kot <kodek11@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IAppConfig;
use function array_intersect;

class SMSApiConfig implements IProviderConfig {
	private const expected = [
		'smsapi_token',
		'smsapi_sender',
	];

	public function __construct(
		private IAppConfig $config,
	) {
	}

	private function getOrFail(string $key): string {
		$val = $this->config->getValueString(Application::APP_ID, $key);
		if (empty($val)) {
			throw new ConfigurationException();
		}
		return $val;
	}

	public function getToken(): string {
		return $this->getOrFail('smsapi_token');
	}

	public function getSender(): string {
		return $this->getOrFail('smsapi_sender');
	}

	public function setToken(string $token): void {
		$this->config->getValueString(Application::APP_ID, 'smsapi_token', $token);
	}

	public function setSender(string $sender): void {
		$this->config->getValueString(Application::APP_ID, 'smsapi_sender', $sender);
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
