<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
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
class PlaySMSConfig extends AGatewayConfig {
	public const SCHEMA = [
		'id' => 'playsms',
		'name' => 'PlaySMS',
		'fields' => [
			['field' => 'url',      'prompt' => 'Please enter your PlaySMS URL:'],
			['field' => 'user',     'prompt' => 'Please enter your PlaySMS username:'],
			['field' => 'password', 'prompt' => 'Please enter your PlaySMS password:'],
		],
	];
}
