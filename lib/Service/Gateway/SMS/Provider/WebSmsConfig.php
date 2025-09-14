<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class WebSmsConfig extends AGatewayConfig {
	protected const expected = [
		'websms_de_user',
		'websms_de_password',
	];

	public function getUser(): string {
		return $this->getOrFail('websms_de_user');
	}

	public function setUser(string $user): void {
		$this->config->setValueString(Application::APP_ID, 'websms_de_user', $user);
	}

	public function getPassword(): string {
		return $this->getOrFail('websms_de_password');
	}

	public function setPassword(string $password): void {
		$this->config->setValueString(Application::APP_ID, 'websms_de_password', $password);
	}
}
