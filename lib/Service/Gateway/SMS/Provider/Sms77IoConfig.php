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
 * @method $this setApiKey(string $apiKey)
 */
class Sms77IoConfig extends AGatewayConfig {
	protected const FIELDS = [
		'api_key',
	];

	#[\Override]
	public static function providerId(): string {
		return 'sms77io';
	}
}
