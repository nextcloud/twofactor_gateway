<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Martin Keßler <martin@moegger.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getApi()
 * @method static setApi(string $api)
 */
class HuaweiE3531Config extends AGatewayConfig {
	public const SMS_SCHEMA = [
		'id' => 'huawei_e3531',
		'name' => 'Huawei E3531',
		'fields' => [
			['field' => 'api', 'prompt' => 'Please enter the base URL of the Huawei E3531 stick: ', 'default' => 'http://192.168.8.1/api'],
		],
	];
}
