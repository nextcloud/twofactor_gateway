<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christian Schrötter <cs@fnx.li>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getApi()
 * @method $this setApi(string $api)
 * @method string getUser()
 * @method $this setUser(string $user)
 * @method string getPassword()
 * @method $this setPassword(string $password)
 */
class ClickatellCentralConfig extends AGatewayConfig {
	protected const expected = [
		'api',
		'user',
		'password',
	];

	#[\Override]
	public static function providerId(): string {
		return 'clickatell_central';
	}
}
