<?php

declare(strict_types=1);

/**
 * @author Rainer Dohmen <rdohmen@pensionmoselblick.de>
 * Nextcloud - Two-factor Gateway for XMPP
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


namespace OCA\TwoFactorGateway\Service\Gateway\XMPP;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Service\Gateway\IGatewayConfig;
use OCP\IAppConfig;

class GatewayConfig implements IGatewayConfig {
	private const expected = [
		'xmpp_sender',
		'xmpp_password',
		'xmpp_server',
		'xmpp_username',
		'xmpp_method',
	];

	public function __construct(
		private IAppConfig $config,
	) {
	}

	private function getOrFail(string $key): string {
		$val = $this->config->getValueString(Application::APP_ID, $key);
		if ($val === '') {
			throw new ConfigurationException();
		}
		return $val;
	}

	public function getSender(): string {
		return $this->getOrFail('xmpp_sender');
	}

	public function setSender(string $sender) {
		$this->config->setValueString(Application::APP_ID, 'xmpp_sender', $sender);
	}

	public function getPassword(): string {
		return $this->getOrFail('xmpp_password');
	}

	public function setPassword(string $password) {
		$this->config->setValueString(Application::APP_ID, 'xmpp_password', $password);
	}

	public function getServer(): string {
		return $this->getOrFail('xmpp_server');
	}

	public function setServer(string $server) {
		$this->config->setValueString(Application::APP_ID, 'xmpp_server', $server);
	}

	public function getUsername(): string {
		return $this->getOrFail('xmpp_username');
	}

	public function setUsername(string $username) {
		$this->config->setValueString(Application::APP_ID, 'xmpp_username', $username);
	}
	public function getMethod(): string {
		return $this->getOrFail('xmpp_method');
	}

	public function setMethod(string $method) {
		$this->config->setValueString(Application::APP_ID, 'xmpp_method', $method);
	}

	#[\Override]
	public function isComplete(): bool {
		$set = $this->config->getAllValues(Application::APP_ID);
		return count(array_intersect($set, self::expected)) === count(self::expected);
	}

	public function remove() {
		foreach (self::expected as $key) {
			$this->config->deleteKey(Application::APP_ID, $key);
		}
	}
}
