<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Paweł Kuffel <pawel@kuffel.io>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getLogin()
 * @method static setLogin(string $login)
 * @method string getPassword()
 * @method static setPassword(string $password)
 * @method string getSender()
 * @method static setSender(string $sender)
 */
class SerwerSMSConfig extends AGatewayConfig {
	public const SCHEMA = [
		'id' => 'serwersms',
		'name' => 'SerwerSMS',
		'fields' => [
			['field' => 'login',    'prompt' => 'Please enter your SerwerSMS.pl API login:'],
			['field' => 'password', 'prompt' => 'Please enter your SerwerSMS.pl API password:'],
			['field' => 'sender',   'prompt' => 'Please enter your SerwerSMS.pl sender name:'],
		],
	];
}
