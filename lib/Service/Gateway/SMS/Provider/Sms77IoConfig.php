<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 André Matthies <a.matthies@sms77.io>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getApiKey()
 * @method static setApiKey(string $apiKey)
 */
class Sms77IoConfig extends AGatewayConfig {
	public const SMS_SCHEMA = [
		'id' => 'sms77io',
		'name' => 'sms77.io',
		'fields' => [
			['field' => 'api_key', 'prompt' => 'Please enter your sms77.io API key:'],
		],
	];
}
