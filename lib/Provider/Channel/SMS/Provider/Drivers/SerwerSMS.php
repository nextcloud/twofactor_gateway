<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Paweł Kuffel <pawel@kuffel.io>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\Drivers;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\AProvider;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

/**
 * @method string getLogin()
 * @method static setLogin(string $login)
 * @method string getPassword()
 * @method static setPassword(string $password)
 * @method string getSender()
 * @method static setSender(string $sender)
 */
class SerwerSMS extends AProvider {
	public const SCHEMA = [
		'name' => 'SerwerSMS',
		'fields' => [
			['field' => 'login',    'prompt' => 'Please enter your SerwerSMS.pl API login:'],
			['field' => 'password', 'prompt' => 'Please enter your SerwerSMS.pl API password:'],
			['field' => 'sender',   'prompt' => 'Please enter your SerwerSMS.pl sender name:'],
		],
	];
	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$login = $this->getLogin();
		$password = $this->getPassword();
		$sender = $this->getSender();
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
				throw new MessageTransmissionException();
			}
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
