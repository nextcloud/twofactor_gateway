<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christian Schrötter <cs@fnx.li>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
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
	public const SCHEMA = [
		'id' => 'clickatell_central',
		'name' => 'Clickatell Central',
		'fields' => [
			['field' => 'api',      'prompt' => 'Please enter your central.clickatell.com API-ID:'],
			['field' => 'user',     'prompt' => 'Please enter your central.clickatell.com username:'],
			['field' => 'password', 'prompt' => 'Please enter your central.clickatell.com password:'],
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

		if ($response->getStatusCode() !== 200 || substr($response->getBody(), 0, 4) !== 'ID: ') {
			throw new MessageTransmissionException($response->getBody());
		}
	}
}
