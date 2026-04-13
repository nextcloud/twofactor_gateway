<?php

declare(strict_types=1);

/**
 * @author Pierre LEROUGE <pierre.lerouge@cegedim.com>
 *
 * Nextcloud - Two-factor Gateway for Cegedim.Cloud Vortext
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
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
 * @method string getPassword()
 * @method static setPassword(string $password)
 * @method string getEndpoint()
 * @method static setEndpoint(string $endpoint)
 */
class CegedimVortext extends AProvider {
	private const ENDPOINTS = [
		'eb-pub' => 'https://messages.cloud.cegedim.com',
		'eb-int' => 'https://vortext.cloud.cegedim.com',
	];

	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	public function createSettings(): Settings {
		return new Settings(
			id: 'cegedim_vortext',
			name: 'Cegedim.Cloud Vortext',
			instructions: 'Available endpoint keys: ' . implode(', ', array_keys(self::ENDPOINTS)),
			fields: [
				new FieldDefinition(
					field: 'user',
					prompt: 'Please enter your Cegedim.Cloud Vortext username:',
				),
				new FieldDefinition(
					field: 'password',
					prompt: 'Please enter your Cegedim.Cloud Vortext password:',
				),
				new FieldDefinition(
					field: 'endpoint',
					prompt: 'Please enter the endpoint key (e.g. eb4):',
				),
			]
		);
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$endpointKey = $this->getEndpoint();
		if (!isset(self::ENDPOINTS[$endpointKey])) {
			throw new MessageTransmissionException('Unknown endpoint key: ' . $endpointKey);
		}
		$baseUrl = self::ENDPOINTS[$endpointKey];

		try {
			$response = $this->client->post($baseUrl . '/sms', [
				'json' => [
					'message' => $message,
					'phoneNumber' => $identifier,
				],
				'auth' => [$this->getUser(), $this->getPassword()],
			]);
			$result = json_decode($response->getBody(), true);
			if (empty($result['messageId'])) {
				throw new MessageTransmissionException('No messageId in response for ' . $identifier);
			}
		} catch (MessageTransmissionException $ex) {
			throw $ex;
		} catch (Exception $ex) {
			throw new MessageTransmissionException($ex->getMessage());
		}
	}
}
