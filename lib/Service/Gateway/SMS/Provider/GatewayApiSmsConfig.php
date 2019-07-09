<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author André Fondse <andre@hetnetwerk.org>
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

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IConfig;

class GatewayApiSmsConfig implements IProviderConfig {

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

	public function getAuthToken(): string {
		return $this->getOrFail('gatewayapisms_authtoken');
	}

	public function setAuthToken(string $authtoken) {
		$this->config->setAppValue(Application::APP_NAME, 'gatewayapisms_authtoken', $authtoken);
	}

	public function getSenderId(): string {
		return $this->getOrFail('gatewayapisms_senderid');
	}

	public function setSenderId(string $senderId) {
		$this->config->setAppValue(Application::APP_NAME, 'gatewayapisms_senderid', $senderId);
	}

	public function isComplete(): bool {
		$set = $this->config->getAppKeys(Application::APP_NAME);
		$expected = [
			'gatewayapisms_authtoken',
			'gatewayapisms_senderid',
		];
		return count(array_intersect($set, $expected)) === count($expected);
	}

}
