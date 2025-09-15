<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getUser()
 * @method $this setUser(string $user)
 * @method string getPassword()
 * @method $this setPassword(string $password)
 */
class WebSmsConfig extends AGatewayConfig {
	protected const expected = [
		'user',
		'password',
	];

	#[\Override]
	public static function providerId(): string {
		return 'websms_de';
	}
}
