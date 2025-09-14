<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 André Matthies <a.matthies@sms77.io>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class Sms77IoConfig extends AGatewayConfig {
	protected const expected = [
		'sms77io_api_key',
	];

	public function getApiKey(): string {
		return $this->getOrFail('sms77io_api_key');
	}

	public function setApiKey(string $apiKey): void {
		$this->config->setValueString(Application::APP_ID, 'sms77io_api_key', $apiKey);
	}
}
