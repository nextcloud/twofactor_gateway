<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Francois Blackburn <blackburnfrancois@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class VoipMsConfig extends AGatewayConfig {
	protected const expected = [
		'voipms_api_username',
		'voipms_api_password',
		'voipms_did',
	];

	public function getUser(): string {
		return $this->getOrFail('voipms_api_username');
	}

	public function setUser(string $user): void {
		$this->config->setValueString(Application::APP_ID, 'voipms_api_username', $user);
	}

	public function getPassword(): string {
		return $this->getOrFail('voipms_api_password');
	}

	public function setPassword(string $password): void {
		$this->config->setValueString(Application::APP_ID, 'voipms_api_password', $password);
	}

	public function getDid(): string {
		return $this->getOrFail('voipms_did');
	}

	public function setDid(string $did): void {
		$this->config->setValueString(Application::APP_ID, 'voipms_did', $did);
	}
}
