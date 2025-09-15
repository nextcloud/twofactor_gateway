<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Ruben de Wit <ruben@iamit.nl>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getApitoken()
 * @method static setApitoken(string $apitoken)
 */
class SpryngSMSConfig extends AGatewayConfig {
	public const SCHEMA = [
		'id' => 'spryng',
		'name' => 'Spryng',
		'fields' => [
			['field' => 'apitoken', 'prompt' => 'Please enter your Spryng api token:'],
		],
	];
}
