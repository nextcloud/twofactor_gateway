<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Fabian Zihlmann <fabian.zihlmann@mybica.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getUsername()
 * @method static setUsername(string $username)
 * @method string getPassword()
 * @method static setPassword(string $password)
 * @method string getSenderid()
 * @method static setSenderid(string $senderid)
 */
class EcallSMSConfig extends AGatewayConfig {
	public const SMS_SCHEMA = [
		'id' => 'ecallsms',
		'name' => 'EcallSMS',
		'fields' => [
			['field' => 'user',      'prompt' => 'Please enter your eCall.ch username:'],
			['field' => 'password',  'prompt' => 'Please enter your eCall.ch password:'],
			['field' => 'sender_id', 'prompt' => 'Please enter your eCall.ch sender ID:'],
		],
	];
}
