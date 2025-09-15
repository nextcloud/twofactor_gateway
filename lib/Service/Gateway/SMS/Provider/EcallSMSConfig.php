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
	protected const SMS_SCHEMA = [
		'username',
		'password',
		/**
		 * preserved without snake case by backward compatibility
		 */
		'senderid',
	];

	#[\Override]
	public static function providerId(): string {
		return 'ecallsms';
	}
}
