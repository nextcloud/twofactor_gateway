<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Kim Syversen <kim.syversen@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getUrl()
 * @method $this setUrl(string $url)
 * @method string getUser()
 * @method $this setUser(string $user)
 * @method string getPassword()
 * @method $this setPassword(string $password)
 * @method string getServiceid()
 * @method $this setServiceid(string $serviceid)
 */
class PuzzelSMSConfig extends AGatewayConfig {
	protected const expected = [
		'url',
		'user',
		'password',
		/**
		 * preserved without snake case by backward compatibility
		 */
		'serviceid',
	];

	#[\Override]
	public static function providerId(): string {
		return 'puzzel';
	}
}
