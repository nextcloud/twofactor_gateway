<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Francois Blackburn <blackburnfrancois@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IAppConfig;
use function array_intersect;

class VoipMsConfig implements IProviderConfig {
	private const expected = [
		'voipms_api_username',
		'voipms_api_password',
		'voipms_did',
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
		return $this->getOrFail('voipms_api_username');
	}

	public function setUser(string $user): void {
		$this->config->getValueString(Application::APP_ID, 'voipms_api_username', $user);
	}

	public function getPassword(): string {
		return $this->getOrFail('voipms_api_password');
	}

	public function setPassword(string $password): void {
		$this->config->getValueString(Application::APP_ID, 'voipms_api_password', $password);
	}

	public function getDid(): string {
		return $this->getOrFail('voipms_did');
	}

	public function setDid(string $did): void {
		$this->config->getValueString(Application::APP_ID, 'voipms_did', $did);
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
