<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IAppConfig;
use function array_intersect;

class SMSGlobalConfig implements IProviderConfig {
	private const expected = [
		'smsglobal_url',
		'smsglobal_user',
		'smsglobal_password',
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

	public function getUrl(): string {
		return $this->getOrFail('smsglobal_url');
	}

	public function setUrl(string $url): void {
		$this->config->getValueString(Application::APP_ID, 'smsglobal_url', $url);
	}

	public function getUser(): string {
		return $this->getOrFail('smsglobal_user');
	}

	public function setUser(string $user): void {
		$this->config->getValueString(Application::APP_ID, 'smsglobal_user', $user);
	}

	public function getPassword(): string {
		return $this->getOrFail('smsglobal_password');
	}

	public function setPassword(string $password): void {
		$this->config->getValueString(Application::APP_ID, 'smsglobal_password', $password);
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
