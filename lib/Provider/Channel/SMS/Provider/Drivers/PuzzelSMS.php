<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Kim Syversen <kim.syversen@gmail.com>
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
 * @method string getServiceid()
 * @method static setServiceid(string $serviceid)
 */
class PuzzelSMS extends AProvider {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	public function createSettings(): Settings {
		return new Settings(
			id: 'puzzel',
			name: 'Puzzel SMS',
			fields: [
				new FieldDefinition(
					field: 'url',
					prompt: 'Please enter your PuzzelSMS URL:',
				),
				new FieldDefinition(
					field: 'user',
					prompt: 'Please enter your PuzzelSMS username:',
				),
				new FieldDefinition(
					field: 'password',
					prompt: 'Please enter your PuzzelSMS password:',
				),
				new FieldDefinition(
					field: 'serviceid',
					prompt: 'Please enter your PuzzelSMS service ID:',
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
						'username' => $this->getUser(),
						'password' => $this->getPassword(),
						'message[0].recipient' => '+' . $identifier,
						'message[0].content' => $message,
						'serviceId' => $this->getServiceid(),
					],
				]
			);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
