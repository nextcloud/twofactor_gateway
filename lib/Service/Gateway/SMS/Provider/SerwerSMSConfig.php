<?php

declare(strict_types=1);

/**
 * @author Paweł Kuffel <pawel@kuffel.io>
 *
 * Nextcloud - Two-factor Gateway for SerwerSMS.pl
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use function array_intersect;
use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IConfig;

class SerwerSMSConfig implements IProviderConfig {
	private const expected = [
		'serwersms_login',
		'serwersms_password',
		'serwersms_sender',
	];

	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	private function getOrFail(string $key): string {
		$val = $this->config->getAppValue(Application::APP_NAME, $key, null);
		if (is_null($val)) {
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

	public function setLogin(string $login) {
		$this->config->setAppValue(Application::APP_NAME, 'serwersms_login', $login);
	}

	public function setPassword(string $password) {
		$this->config->setAppValue(Application::APP_NAME, 'serwersms_password', $password);
	}

	public function setSender(string $sender) {
		$this->config->setAppValue(Application::APP_NAME, 'serwersms_sender', $sender);
	}

	public function isComplete(): bool {
		$set = $this->config->getAppKeys(Application::APP_NAME);
		return count(array_intersect($set, self::expected)) === count(self::expected);
	}

	public function remove() {
		foreach (self::expected as $key) {
			$this->config->deleteAppValue(Application::APP_NAME, $key);
		}
	}
}
