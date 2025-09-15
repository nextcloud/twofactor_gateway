<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Henning Bopp <henning.bopp@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getUser()
 * @method static setUser(string $user)
 *
 * @method string getApikey()
 * @method static setApikey(string $apikey)
 */
class ClickSendConfig extends AGatewayConfig {
	protected const SMS_SCHEMA = [
		'user',
		'apikey',
	];

	#[\Override]
	public static function providerId(): string {
		return 'clicksend';
	}
}
