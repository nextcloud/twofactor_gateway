<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * Nextcloud - Two-factor Gateway
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

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IConfig;
use function array_intersect;

class PlaySMSConfig implements IProviderConfig {
	private const expected = [
		'playsms_url',
		'playsms_user',
		'playsms_password',
	];

	public function __construct(
		private IConfig $config,
	) {
	}

	private function getOrFail(string $key): string {
		$val = $this->config->getAppValue(Application::APP_ID, $key, null);
		if (is_null($val)) {
			throw new ConfigurationException();
		}
		return $val;
	}

	public function getUrl(): string {
		return $this->getOrFail('playsms_url');
	}

	public function setUrl(string $url): void {
		$this->config->setAppValue(Application::APP_ID, 'playsms_url', $url);
	}

	public function getUser(): string {
		return $this->getOrFail('playsms_user');
	}

	public function setUser(string $user): void {
		$this->config->setAppValue(Application::APP_ID, 'playsms_user', $user);
	}

	public function getPassword(): string {
		return $this->getOrFail('playsms_password');
	}

	public function setPassword(string $password): void {
		$this->config->setAppValue(Application::APP_ID, 'playsms_password', $password);
	}

	#[\Override]
	public function isComplete(): bool {
		$set = $this->config->getAppKeys(Application::APP_ID);
		return count(array_intersect($set, self::expected)) === count(self::expected);
	}

	#[\Override]
	public function remove() {
		foreach (self::expected as $key) {
			$this->config->deleteAppValue(Application::APP_ID, $key);
		}
	}
}
