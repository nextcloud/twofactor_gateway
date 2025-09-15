<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Rainer Dohmen <rdohmen@pensionmoselblick.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\XMPP;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getSender()
 * @method static setSender(string $sender)
 * @method string getPassword()
 * @method static setPassword(string $password)
 * @method string getServer()
 * @method static setServer(string $server)
 * @method string getUsername()
 * @method static setUsername(string $username)
 * @method string getMethod()
 * @method static setMethod(string $method)
 */
class GatewayConfig extends AGatewayConfig {
	public const SCHEMA = [
		'id' => 'xmpp',
		'name' => 'XMPP',
		'fields' => [
			['field' => 'sender',   'prompt' => 'Please enter your sender XMPP-JID:'],
			['field' => 'password', 'prompt' => 'Please enter your sender XMPP password:'],
			['field' => 'server',   'prompt' => 'Please enter full path to access REST/HTTP API:'],
			['field' => 'username'],
			['field' => 'method',   'prompt' => 'Please enter 1 or 2 for XMPP sending option:'],
		],
	];
}
