<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Fabian Zihlmann <fabian.zihlmann@mybica.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\Drivers;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\AProvider;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

/**
 * @method string getUsername()
 * @method static setUsername(string $username)
 * @method string getPassword()
 * @method static setPassword(string $password)
 * @method string getSender()
 * @method static setSender(string $sender)
 */
class EcallSMS extends AProvider {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	public function createSettings(): Settings {
		return new Settings(
			id: 'ecallsms',
			name: 'EcallSMS',
			fields: [
				new FieldDefinition(
					field: 'username',
					prompt: 'Please enter your eCall.ch username:',
				),
				new FieldDefinition(
					field: 'password',
					prompt: 'Please enter your eCall.ch password:',
				),
				new FieldDefinition(
					field: 'sender',
					prompt: 'Please enter your eCall.ch sender ID:',
				),
			]
		);
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$user = $this->getUsername();
		$password = $this->getPassword();
		$sender = $this->getSender();
		try {
			$this->client->get('https://url.ecall.ch/api/sms', [
				'query' => [
					'username' => $user,
					'password' => $password,
					'Callback' => $sender,
					'address' => $identifier,
					'message' => $message,
				],
			]);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
