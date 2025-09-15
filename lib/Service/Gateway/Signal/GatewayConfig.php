<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\Signal;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class GatewayConfig extends AGatewayConfig {
	public const SMS_SCHEMA = [
		'url',
	];

	#[\Override]
	public static function providerId(): string {
		return 'signal';
	}
}
