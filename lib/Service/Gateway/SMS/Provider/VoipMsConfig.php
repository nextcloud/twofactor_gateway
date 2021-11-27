<?php

declare(strict_types=1);

/**
 * @author Francois Blackburn <blackburnfrancois@gmail.com>
 *
 * Nextcloud - Two-factor Gateway for Telegram
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

class VoipMsConfig implements IProviderConfig {
	private const expected = [
		'voipms_api_username',
		'voipms_api_password',
		'voipms_did',
	];

	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	private function getOrFail(string $key): string {
		$val = $this->config->getAppValue(Application::APP_ID, $key, null);
		if (is_null($val)) {
			throw new ConfigurationException();
		}
		return $val;
	}

	public function getUser(): string {
		return $this->getOrFail('voipms_api_username');
	}

	public function setUser(string $user) {
		$this->config->setAppValue(Application::APP_ID, 'voipms_api_username', $user);
	}

	public function getPassword(): string {
		return $this->getOrFail('voipms_api_password');
	}

	public function setPassword(string $password) {
		$this->config->setAppValue(Application::APP_ID, 'voipms_api_password', $password);
	}

	public function getDid(): string {
		return $this->getOrFail('voipms_did');
	}

	public function setDid(string $did) {
		$this->config->setAppValue(Application::APP_ID, 'voipms_did', $did);
	}

	public function isComplete(): bool {
		$set = $this->config->getAppKeys(Application::APP_ID);
		return count(array_intersect($set, self::expected)) === count(self::expected);
	}
	
	public function remove() {
		foreach (self::expected as $key) {
			$this->config->deleteAppValue(Application::APP_ID, $key);
		}
	}
}
