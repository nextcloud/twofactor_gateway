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
 * @method $this setLogin(string $login)
 * @method string getPassword()
 * @method $this setPassword(string $password)
 * @method string getSender()
 * @method $this setSender(string $sender)
 */
class SerwerSMSConfig extends AGatewayConfig {
	protected const expected = [
		'login',
		'password',
		'sender',
	];

	#[\Override]
	public static function providerId(): string {
		return 'serwersms';
	}
}
