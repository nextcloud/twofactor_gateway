<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Fabian Zihlmann <fabian.zihlmann@mybica.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IAppConfig;
use function array_intersect;

class EcallSMSConfig implements IProviderConfig {
	private const expected = [
		'ecallsms_username',
		'ecallsms_password',
		'ecallsms_senderid',
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

	public function getUser(): string {
		return $this->getOrFail('ecallsms_username');
	}

	public function setUser(string $user): void {
		$this->config->getValueString(Application::APP_ID, 'ecallsms_username', $user);
	}

	public function getPassword(): string {
		return $this->getOrFail('ecallsms_password');
	}

	public function setPassword(string $password): void {
		$this->config->getValueString(Application::APP_ID, 'ecallsms_password', $password);
	}

	public function getSenderId(): string {
		return $this->getOrFail('ecallsms_senderid');
	}

	public function setSenderId(string $senderid): void {
		$this->config->getValueString(Application::APP_ID, 'ecallsms_senderid', $senderid);
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
