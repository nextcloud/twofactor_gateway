<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christian Schrötter <cs@fnx.li>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getApikey()
 * @method static setApikey(string $apikey)
 *
 * @method string getFrom()
 * @method static setFrom(string $from)
 */
class ClickatellPortalConfig extends AGatewayConfig {
	public const SCHEMA = [
		'id' => 'clickatell_portal',
		'name' => 'Clickatell Portal',
		'fields' => [
			['field' => 'apikey', 'prompt' => 'Please enter your portal.clickatell.com API-Key:'],
			['field' => 'from',   'prompt' => 'Please enter your sender number for two-way messaging (empty = one-way): ', 'optional' => true],
		],
	];
}
