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
	protected const FIELDS = [
		'token',
		'sender',
	];

	#[\Override]
	public static function providerId(): string {
		return 'smsapi';
	}
}
