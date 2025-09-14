<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Paweł Kuffel <pawel@kuffel.io>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class SerwerSMSConfig extends AGatewayConfig {
	protected const expected = [
		'serwersms_login',
		'serwersms_password',
		'serwersms_sender',
	];

	public function getLogin(): string {
		return $this->getOrFail('serwersms_login');
	}

	public function getPassword(): string {
		return $this->getOrFail('serwersms_password');
	}

	public function getSender(): string {
		return $this->getOrFail('serwersms_sender');
	}

	public function setLogin(string $login): void {
		$this->config->setValueString(Application::APP_ID, 'serwersms_login', $login);
	}

	public function setPassword(string $password): void {
		$this->config->setValueString(Application::APP_ID, 'serwersms_password', $password);
	}

	public function setSender(string $sender): void {
		$this->config->setValueString(Application::APP_ID, 'serwersms_sender', $sender);
	}
}
