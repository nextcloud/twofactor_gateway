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
class VoipMsConfig extends AGatewayConfig {
	public const SMS_SCHEMA = [
		'id' => 'voipms',
		'name' => 'VoIP.ms',
		'fields' => [
			['field' => 'api_user',     'prompt' => 'Please enter your VoIP.ms API username:'],
			['field' => 'api_password', 'prompt' => 'Please enter your VoIP.ms API password:'],
			['field' => 'did',          'prompt' => 'Please enter your VoIP.ms DID:'],
		],
	];
}
