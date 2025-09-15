<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\Signal;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getUrl()
 * @method static setUrl(string $url)
 */
class GatewayConfig extends AGatewayConfig {
	public const SMS_SCHEMA = [
		'id' => 'signal',
		'name' => 'Signal',
		'fields' => [
			['field' => 'url', 'prompt' => 'Please enter the URL of the Signal gateway (leave blank to use default):', 'default' => 'http://localhost:5000'],
		],
	];
}
