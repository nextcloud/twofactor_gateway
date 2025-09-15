<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Marcin Kot <kodek11@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getToken()
 * @method static setToken(string $token)
 * @method string getSender()
 * @method static setSender(string $sender)
 */
class SMSApiConfig extends AGatewayConfig {
	public const SMS_SCHEMA = [
		'id' => 'smsapi',
		'name' => 'SMSAPI',
		'fields' => [
			['field' => 'token', 'prompt' => 'Please enter your SMSApi.com API token:'],
			['field' => 'sender','prompt' => 'Please enter your SMSApi.com sender name:'],
		],
	];
}
