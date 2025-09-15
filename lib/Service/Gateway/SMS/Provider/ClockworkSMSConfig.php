<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Mario Klug <mario.klug@sourcefactory.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getApitoken()
 * @method static setApitoken(string $apitoken)
 */
class ClockworkSMSConfig extends AGatewayConfig {
	protected const FIELDS = [
		/**
		 * preserved without snake case by backward compatibility
		 */
		'apitoken'
	];

	#[\Override]
	public static function providerId(): string {
		return 'clockworksms';
	}
}
