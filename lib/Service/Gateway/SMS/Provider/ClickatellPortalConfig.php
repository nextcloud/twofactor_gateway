<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christian Schrötter <cs@fnx.li>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getApikey()
 * @method static setApikey(string $apikey)
 *
 * @method string getFrom()
 * @method static setFrom(string $from)
 */
class ClickatellPortalConfig extends AGatewayConfig {
	public const SMS_SCHEMA = [
		'apikey',
		'from',
	];

	#[\Override]
	public static function providerId(): string {
		return 'clickatell_portal';
	}

	public function deleteFromNumber(): void {
		$this->config->deleteKey(Application::APP_ID, 'clickatell_portal_from');
	}
}
