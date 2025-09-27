<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Pascal Clémot <pascal.clemot@free.fr>
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
 * @method string getUrl()
 * @method static setUrl(string $url)
 * @method string getUser()
 * @method static setUser(string $user)
 * @method string getPassword()
 * @method static setPassword(string $password)
 */
class PlaySMS extends AProvider {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	public function createSettings(): Settings {
		return new Settings(
			id: 'playsms',
			name: 'PlaySMS',
			fields: [
				new FieldDefinition(
					field: 'url',
					prompt: 'Please enter your PlaySMS URL:',
				),
				new FieldDefinition(
					field: 'user',
					prompt: 'Please enter your PlaySMS username:',
				),
				new FieldDefinition(
					field: 'password',
					prompt: 'Please enter your PlaySMS password:',
				),
			]
		);
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		try {
			$this->client->get(
				$this->getUrl(),
				[
					'query' => [
						'app' => 'ws',
						'u' => $this->getUser(),
						'h' => $this->getPassword(),
						'op' => 'pv',
						'to' => $identifier,
						'msg' => $message,
					],
				]
			);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
