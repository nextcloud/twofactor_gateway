<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

/**
 * @method string getUser()
 * @method static setUser(string $user)
 * @method string getPassword()
 * @method static setPassword(string $password)
 */
class WebSms extends AProvider {
	public const SCHEMA = [
		'id' => 'websms_de',
		'name' => 'WebSMS.de',
		'fields' => [
			['field' => 'user',     'prompt' => 'Please enter your websms.de username:'],
			['field' => 'password', 'prompt' => 'Please enter your websms.de password:'],
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
		$user = $this->getUser();
		$password = $this->getPassword();
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
