<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\TwoFactorGateway\Service\Gateway\Telegram;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getBotToken()
 * @method static setBotToken(string $botToken)
 */
class GatewayConfig extends AGatewayConfig {
	public const SCHEMA = [
		'id' => 'telegram',
		'name' => 'Telegram',
		'fields' => [
			['field' => 'bot_token', 'prompt' => 'Please enter your Telegram bot token:'],
		],
		'bot_token',
	];
}
