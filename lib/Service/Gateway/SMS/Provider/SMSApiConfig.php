<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Marcin Kot <kodek11@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class SMSApiConfig extends AGatewayConfig {
	protected const expected = [
		'smsapi_token',
		'smsapi_sender',
	];

	public function getToken(): string {
		return $this->getOrFail('smsapi_token');
	}

	public function getSender(): string {
		return $this->getOrFail('smsapi_sender');
	}

	public function setToken(string $token): void {
		$this->config->getValueString(Application::APP_ID, 'smsapi_token', $token);
	}

	public function setSender(string $sender): void {
		$this->config->getValueString(Application::APP_ID, 'smsapi_sender', $sender);
	}
}
