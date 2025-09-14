<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Fabian Zihlmann <fabian.zihlmann@mybica.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class EcallSMSConfig extends AGatewayConfig {
	protected const expected = [
		'ecallsms_username',
		'ecallsms_password',
		'ecallsms_senderid',
	];

	public function getUser(): string {
		return $this->getOrFail('ecallsms_username');
	}

	public function setUser(string $user): void {
		$this->config->setValueString(Application::APP_ID, 'ecallsms_username', $user);
	}

	public function getPassword(): string {
		return $this->getOrFail('ecallsms_password');
	}

	public function setPassword(string $password): void {
		$this->config->setValueString(Application::APP_ID, 'ecallsms_password', $password);
	}

	public function getSenderId(): string {
		return $this->getOrFail('ecallsms_senderid');
	}

	public function setSenderId(string $senderid): void {
		$this->config->setValueString(Application::APP_ID, 'ecallsms_senderid', $senderid);
	}
}
