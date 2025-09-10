<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christian Schrötter <cs@fnx.li>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IAppConfig;

class ClickatellCentralConfig implements IProviderConfig {
	private const expected = [
		'clickatell_central_api',
		'clickatell_central_user',
		'clickatell_central_password',
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

	public function getApi(): string {
		return $this->getOrFail('clickatell_central_api');
	}

	public function setApi(string $api): void {
		$this->config->setValueString(Application::APP_ID, 'clickatell_central_api', $api);
	}

	public function getUser(): string {
		return $this->getOrFail('clickatell_central_user');
	}

	public function setUser(string $user): void {
		$this->config->setValueString(Application::APP_ID, 'clickatell_central_user', $user);
	}

	public function getPassword(): string {
		return $this->getOrFail('clickatell_central_password');
	}

	public function setPassword(string $password): void {
		$this->config->setValueString(Application::APP_ID, 'clickatell_central_password', $password);
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
