<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\TwoFactorGateway\Service\Gateway\Telegram;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;
use OCP\IAppConfig;
use OCP\IConfig;

class GatewayConfig extends AGatewayConfig {
	protected const expected = [
		'telegram_bot_token',
	];

	public function __construct(
		public IAppConfig $config,
		private IConfig $globalConfig,
	) {
	}

	public function getBotToken(): string {
		return $this->getOrFail('telegram_bot_token');
	}

	public function setBotToken(string $token): void {
		$this->globalConfig->setAppValue(Application::APP_ID, 'telegram_bot_token', $token);
	}
}
