<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Pascal Clémot <pascal.clemot@free.fr>
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
class SMSGlobal extends AProvider {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	public function createSettings(): Settings {
		return new Settings(
			id: 'smsglobal',
			name: 'SMSGlobal',
			fields: [
				new FieldDefinition(
					field: 'url',
					prompt: 'Please enter your SMSGlobal http-api:',
					default: 'https://api.smsglobal.com/http-api.php',
				),
				new FieldDefinition(
					field: 'user',
					prompt: 'Please enter your SMSGlobal username (for http-api):',
				),
				new FieldDefinition(
					field: 'password',
					prompt: 'Please enter your SMSGlobal password (for http-api):',
				),
			]
		);
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$to = str_replace('+', '', $identifier);

		try {
			$this->client->get(
				$this->getUrl(),
				[
					'query' => [
						'action' => 'sendsms',
						'user' => $this->getUser(),
						'password' => $this->getPassword(),
						'origin' => 'nextcloud',
						'from' => 'nextcloud',
						'to' => $to,
						'text' => $message,
						'clientcharset' => 'UTF-8',
						'detectcharset' => 1
					],
				]
			);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
