<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Henning Bopp <henning.bopp@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class ClickSendConfig extends AGatewayConfig {
	protected const expected = [
		'clicksend_user',
		'clicksend_apikey',
	];

	public function getUser(): string {
		return $this->getOrFail('clicksend_user');
	}

	public function setUser(string $user): void {
		$this->config->getValueString(Application::APP_ID, 'clicksend_user', $user);
	}

	public function getApiKey(): string {
		return $this->getOrFail('clicksend_apikey');
	}

	public function setApiKey(string $password): void {
		$this->config->getValueString(Application::APP_ID, 'clicksend_apikey', $password);
	}
}
