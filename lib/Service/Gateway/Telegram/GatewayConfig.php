<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\TwoFactorGateway\Service\Gateway\Telegram;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;
use OCP\IAppConfig;
use OCP\IConfig;

class GatewayConfig extends AGatewayConfig {
	public const SMS_SCHEMA = [
		'bot_token',
	];

	#[\Override]
	public static function providerId(): string {
		return 'telegram';
	}

	public function __construct(
		public IAppConfig $config,
		private IConfig $globalConfig,
	) {
	}
}
