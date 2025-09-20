<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Henning Bopp <henning.bopp@gmail.com>
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
 *
 * @method string getApikey()
 * @method static setApikey(string $apikey)
 */
class ClickSend extends AProvider {
	public const SCHEMA = [
		'name' => 'ClickSend',
		'fields' => [
			['field' => 'user', 'prompot' => 'Please enter your clicksend.com username:'],
			['field' => 'apikey', 'prompot' => 'Please enter your clicksend.com api key (or subuser password):'],
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
		$apiKey = $this->getApiKey();
		$username = $this->getUser();
		try {
			$this->client->get('https://api-mapper.clicksend.com/http/v2/send.php', [
				'query' => [
					'method' => 'http',
					'username' => $username,
					'key' => $apiKey,
					'to' => $identifier,
					'message' => $message,
					'senderid' => 'nextcloud'
				],
			]);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
