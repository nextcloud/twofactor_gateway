<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Henning Bopp <henning.bopp@gmail.com>
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
 * @method string getUser()
 * @method static setUser(string $user)
 *
 * @method string getApikey()
 * @method static setApikey(string $apikey)
 */
class ClickSend extends AProvider {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	public function createSettings(): Settings {
		return new Settings(
			id: 'clicksend',
			name: 'ClickSend',
			fields: [
				new FieldDefinition(
					field: 'user',
					prompt: 'Please enter your clicksend.com username:',
				),
				new FieldDefinition(
					field: 'apikey',
					prompt: 'Please enter your clicksend.com api key (or subuser password):',
				),
			]
		);
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
