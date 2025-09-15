<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Francois Blackburn <blackburnfrancois@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getApiUser()
 * @method static setApiUser(string $apiUser)
 * @method string getApiPassword()
 * @method static setApiPassword(string $apiPassword)
 * @method string getDid()
 * @method static setDid(string $did)
 */
class VoipbusterConfig extends AGatewayConfig {
	protected const SMS_SCHEMA = [
		'api_username',
		'api_password',
		'did',
	];

	#[\Override]
	public static function providerId(): string {
		return 'voipbuster';
	}
}
