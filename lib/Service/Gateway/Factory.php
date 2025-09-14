<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway;

use Exception;
use OCA\TwoFactorGateway\Service\Gateway\Signal\Gateway as SignalGateway;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Gateway as SMSGateway;
use OCA\TwoFactorGateway\Service\Gateway\Telegram\Gateway as TelegramGateway;
use OCA\TwoFactorGateway\Service\Gateway\XMPP\Gateway as XMPPGateway;

class Factory {
	public function __construct(
		private SignalGateway $signalGateway,
		private SMSGateway $smsGateway,
		private TelegramGateway $telegramGateway,
		private XMPPGateway $xmppGateway,
	) {
	}

	public function getGateway(string $name): IGateway {
		return match ($name) {
			'signal' => $this->signalGateway,
			'sms' => $this->smsGateway,
			'telegram' => $this->telegramGateway,
			'xmpp' => $this->xmppGateway,
			default => throw new Exception("Invalid gateway $name"),
		};
	}
}
