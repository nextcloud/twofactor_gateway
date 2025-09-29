<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Francois Blackburn <blackburnfrancois@gmail.com>
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
 * @method string getApiUser()
 * @method static setApiUser(string $apiUser)
 * @method string getApiPassword()
 * @method static setApiPassword(string $apiPassword)
 * @method string getDid()
 * @method static setDid(string $did)
 */
class Voipbuster extends AProvider {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	public function createSettings(): Settings {
		return new Settings(
			id: 'voipbuster',
			name: 'Voipbuster',
			fields: [
				new FieldDefinition(
					field: 'api_user',
					prompt: 'Please enter your Voipbuster API username:',
				),
				new FieldDefinition(
					field: 'api_password',
					prompt: 'Please enter your Voipbuster API password:',
				),
				new FieldDefinition(
					field: 'did',
					prompt: 'Please enter your Voipbuster DID:',
				),
			]
		);
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$user = $this->getApiUser();
		$password = $this->getApiPassword();
		$did = $this->getDid();
		try {
			$this->client->get('https://www.voipbuster.com/myaccount/sendsms.php', [
				'query' => [
					'username' => $user,
					'password' => $password,
					'from' => $did,
					'to' => $identifier,
					'text' => $message,
				],
			]);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
