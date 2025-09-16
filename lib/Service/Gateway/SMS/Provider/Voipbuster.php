<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Francois Blackburn <blackburnfrancois@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
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
	public const SCHEMA = [
		'id' => 'voipbuster',
		'name' => 'Voipbuster',
		'fields' => [
			['field' => 'api_user',     'prompt' => 'Please enter your Voipbuster API username:'],
			['field' => 'api_password', 'prompt' => 'Please enter your Voipbuster API password:'],
			['field' => 'did',          'prompt' => 'Please enter your Voipbuster DID:'],
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
