<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getUser()
 * @method static setUser(string $user)
 * @method string getPassword()
 * @method static setPassword(string $password)
 */
class WebSmsConfig extends AGatewayConfig {
	public const SMS_SCHEMA = [
		'id' => 'websms_de',
		'name' => 'WebSMS.de',
		'fields' => [
			['field' => 'user',     'prompt' => 'Please enter your websms.de username:'],
			['field' => 'password', 'prompt' => 'Please enter your websms.de password:'],
		],
	];
}
