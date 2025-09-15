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
	public const SCHEMA = [
		'id' => 'smsglobal',
		'name' => 'SMSGlobal',
		'fields' => [
			['field' => 'url',      'prompt' => 'Please enter your SMSGlobal http-api:', 'default' => 'https://api.smsglobal.com/http-api.php'],
			['field' => 'user',     'prompt' => 'Please enter your SMSGlobal username (for http-api):'],
			['field' => 'password', 'prompt' => 'Please enter your SMSGlobal password (for http-api):'],
		],
	];
}
