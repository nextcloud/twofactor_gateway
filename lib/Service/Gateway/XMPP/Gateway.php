<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Rainer Dohmen <rdohmen@pensionmoselblick.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\XMPP;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCP\IAppConfig;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class Gateway implements IGateway {

	public function __construct(
		public GatewayConfig $gatewayConfig,
		public IAppConfig $config,
		private LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function send(IUser $user, string $identifier, string $message, array $extra = []) {
		$this->logger->debug("sending xmpp message to $identifier, message: $message");

		$sender = $this->gatewayConfig->getSender();
		$password = $this->gatewayConfig->getPassword();
		$server = $this->gatewayConfig->getServer();
		$method = $this->gatewayConfig->getMethod();
		$user = $this->gatewayConfig->getUsername();
		$url = $server . $identifier;

		if ($method === '1') {
			$from = $user;
		}
		if ($method === '2') {
			$from = $sender;
		}
		$this->logger->debug("URL: $url, sender: $sender, method: $method");

		try {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
			curl_setopt($ch, CURLOPT_USERPWD, $from . ':' . $password);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/plain']);
			$result = curl_exec($ch);
			curl_close($ch);
			$this->logger->debug("XMPP message to $identifier sent");
		} catch (\Exception) {
			throw new MessageTransmissionException();
		}
	}
}
