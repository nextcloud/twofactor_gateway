<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Rainer Dohmen <rdohmen@pensionmoselblick.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

	public function setSender(string $sender): void {
		$this->config->setValueString(Application::APP_ID, 'xmpp_sender', $sender);
	}

	public function getPassword(): string {
		return $this->getOrFail('xmpp_password');
	}

	public function setPassword(string $password): void {
		$this->config->setValueString(Application::APP_ID, 'xmpp_password', $password);
	}

	public function getServer(): string {
		return $this->getOrFail('xmpp_server');
	}

	public function setServer(string $server): void {
		$this->config->setValueString(Application::APP_ID, 'xmpp_server', $server);
	}

	public function getUsername(): string {
		return $this->getOrFail('xmpp_username');
	}

	public function setUsername(string $username): void {
		$this->config->setValueString(Application::APP_ID, 'xmpp_username', $username);
	}
	public function getMethod(): string {
		return $this->getOrFail('xmpp_method');
	}

	public function setMethod(string $method): void {
		$this->config->setValueString(Application::APP_ID, 'xmpp_method', $method);
	}

	#[\Override]
	public function isComplete(): bool {
		$set = $this->config->getAllValues(Application::APP_ID);
		return count(array_intersect($set, self::expected)) === count(self::expected);
	}

	#[\Override]
	public function remove(): void {
		foreach (self::expected as $key) {
			$this->config->deleteKey(Application::APP_ID, $key);
		}
	}
}
