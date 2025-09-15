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
 * @method static setApi(string $api)
 * @method string getUser()
 * @method static setUser(string $user)
 * @method string getPassword()
 * @method static setPassword(string $password)
 */
class ClickatellCentralConfig extends AGatewayConfig {
	public const SCHEMA = [
		'id' => 'clickatell_central',
		'name' => 'Clickatell Central',
		'fields' => [
			['field' => 'api',      'prompt' => 'Please enter your central.clickatell.com API-ID:'],
			['field' => 'user',     'prompt' => 'Please enter your central.clickatell.com username:'],
			['field' => 'password', 'prompt' => 'Please enter your central.clickatell.com password:'],
		],
	];
}
