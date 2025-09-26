<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christian Schrötter <cs@fnx.li>
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
 * @method string getApi()
 * @method static setApi(string $api)
 * @method string getUser()
 * @method static setUser(string $user)
 * @method string getPassword()
 * @method static setPassword(string $password)
 */
class ClickatellCentral extends AProvider {

	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	public function createSettings(): Settings {
		return new Settings(
			id: 'clickatell_central',
			name: 'Clickatell Central',
			fields: [
				new FieldDefinition(
					field: 'api',
					prompt: 'Please enter your central.clickatell.com API-ID:',
				),
				new FieldDefinition(
					field: 'user',
					prompt: 'Please enter your central.clickatell.com username:',
				),
				new FieldDefinition(
					field: 'password',
					prompt: 'Please enter your central.clickatell.com password:',
				),
			]
		);
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		try {
			$response = $this->client->get(vsprintf('https://api.clickatell.com/http/sendmsg?user=%s&password=%s&api_id=%u&to=%s&text=%s', [
				urlencode($this->getUser()),
				urlencode($this->getPassword()),
				$this->getApi(),
				urlencode($identifier),
				urlencode($message),
			]));
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}

		$body = (string)$response->getBody();
		if ($response->getStatusCode() !== 200 || substr($body, 0, 4) !== 'ID: ') {
			throw new MessageTransmissionException($body);
		}
	}
}
