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
 * @method $this setApi(string $api)
 */
class HuaweiE3531Config extends AGatewayConfig {
	protected const FIELDS = [
		'api',
	];

	#[\Override]
	public static function providerId(): string {
		return 'huawei_e3531';
	}
}
