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
class VoipMs extends AProvider {
	public const SCHEMA = [
		'name' => 'VoIP.ms',
		'fields' => [
			['field' => 'api_user',     'prompt' => 'Please enter your VoIP.ms API username:'],
			['field' => 'api_password', 'prompt' => 'Please enter your VoIP.ms API password:'],
			['field' => 'did',          'prompt' => 'Please enter your VoIP.ms DID:'],
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
		$user = $this->getApiUser();
		$password = $this->getApiPassword();
		$did = $this->getDid();
		try {
			$this->client->get('https://voip.ms/api/v1/rest.php', [
				'query' => [
					'api_username' => $user,
					'api_password' => $password,
					'method' => 'sendSMS',
					'did' => $did,
					'dst' => $identifier,
					'message' => $message,
				],
			]);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
