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
	protected const FIELDS = [
		'login',
		'password',
		'sender',
	];

	#[\Override]
	public static function providerId(): string {
		return 'serwersms';
	}
}
