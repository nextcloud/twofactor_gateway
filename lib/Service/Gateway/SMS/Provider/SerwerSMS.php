<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Paweł Kuffel <pawel@kuffel.io>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class SerwerSMS implements IProvider {
	public const PROVIDER_ID = 'serwersms';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		public SerwerSMSConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$login = $this->config->getLogin();
		$password = $this->config->getPassword();
		$sender = $this->config->getSender();
		try {
			$response = $this->client->post('https://api2.serwersms.pl/messages/send_sms', [
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'json' => [
					'username' => $login,
					'password' => $password,
					'phone' => $identifier,
					'text' => $message,
					'sender' => $sender,
				],
			]);

			$responseData = json_decode($response->getBody(), true);

			if ($responseData['success'] !== true) {
				throw new SmsTransmissionException();
			}
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}
}
