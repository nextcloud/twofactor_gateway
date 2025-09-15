<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class WebSms implements IProvider {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
		public WebSmsConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$user = $this->config->getUser();
		$password = $this->config->getPassword();
		try {
			$this->client->post('https://api.websms.com/rest/smsmessaging/text', [
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode("$user:$password"),
					'Content-Type' => 'application/json',
				],
				'json' => [
					'messageContent' => $message,
					'test' => false,
					'recipientAddressList' => [$identifier],
				],
			]);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
