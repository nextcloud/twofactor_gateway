<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Paweł Kuffel <pawel@kuffel.io>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IAppConfig;
use function array_intersect;

class SerwerSMSConfig implements IProviderConfig {
	private const expected = [
		'serwersms_login',
		'serwersms_password',
		'serwersms_sender',
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

	public function getLogin(): string {
		return $this->getOrFail('serwersms_login');
	}

	public function getPassword(): string {
		return $this->getOrFail('serwersms_password');
	}

	public function getSender(): string {
		return $this->getOrFail('serwersms_sender');
	}

	public function setLogin(string $login): void {
		$this->config->getValueString(Application::APP_ID, 'serwersms_login', $login);
	}

	public function setPassword(string $password): void {
		$this->config->getValueString(Application::APP_ID, 'serwersms_password', $password);
	}

	public function setSender(string $sender): void {
		$this->config->getValueString(Application::APP_ID, 'serwersms_sender', $sender);
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
