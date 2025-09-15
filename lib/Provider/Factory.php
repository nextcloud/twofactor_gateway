<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider;

use OCA\TwoFactorGateway\Exception\InvalidProviderException;

class Factory {

	public function __construct(
		private SignalProvider $signalProvider,
		private SmsProvider $smsProvider,
		private TelegramProvider $telegramProvider,
		private XMPPProvider $xmppProvider,
	) {
	}

	/**
	 * @throws InvalidProviderException
	 */
	public function getProvider(string $name): AProvider {
		return match (strtolower($name)) {
			'signal' => $this->signalProvider,
			'sms' => $this->smsProvider,
			'telegram' => $this->telegramProvider,
			'xmpp' => $this->xmppProvider,
			default => throw new InvalidProviderException(),
		};
	}
}
