<?php

declare(strict_types=1);

/**
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * Nextcloud - Two-factor Gateway for SipGate
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

class SipGateConfig implements IProviderConfig {
	private const expected = [
		'sipgate_token_id',
		'sipgate_access_token',
		'sipgate_web_sms_extension',
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

	public function getTokenId(): string {
		return $this->getOrFail('sipgate_token_id');
	}

	public function setTokenId(string $tokenId) {
		$this->config->setAppValue(Application::APP_ID, 'sipgate_token_id', $tokenId);
	}

	public function getAccessToken(): string {
		return $this->getOrFail('sipgate_access_token');
	}

	public function setAccessToken(string $accessToken) {
		$this->config->setAppValue(Application::APP_ID, 'sipgate_access_token', $accessToken);
	}

	public function getWebSmsExtension(): string {
		return $this->getOrFail('sipgate_web_sms_extension');
	}

	public function setWebSmsExtension(string $webSmsExtension) {
		$this->config->setAppValue(Application::APP_ID, 'sipgate_web_sms_extension', $webSmsExtension);
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
