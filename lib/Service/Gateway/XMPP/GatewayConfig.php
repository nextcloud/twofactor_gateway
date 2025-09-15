<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Rainer Dohmen <rdohmen@pensionmoselblick.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\TwoFactorGateway\Service\Gateway\XMPP;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class GatewayConfig extends AGatewayConfig {
	protected const FIELDS = [
		'sender',
		'password',
		'server',
		'username',
		'method',
	];

	#[\Override]
	public static function providerId(): string {
		return 'xmpp';
	}
}
