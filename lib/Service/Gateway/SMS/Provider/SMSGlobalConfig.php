<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getUrl()
 * @method static setUrl(string $url)
 * @method string getUser()
 * @method static setUser(string $user)
 * @method string getPassword()
 * @method static setPassword(string $password)
 */
class SMSGlobalConfig extends AGatewayConfig {
	protected const SMS_SCHEMA = [
		'url',
		'user',
		'password',
	];

	#[\Override]
	public static function providerId(): string {
		return 'smsglobal';
	}
}
