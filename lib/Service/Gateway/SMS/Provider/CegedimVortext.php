<?php

declare(strict_types=1);

/**
 * @author Pierre LEROUGE <pierre.lerouge@cegedim.com>
 *
 * Nextcloud - Two-factor Gateway for Vortext
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
 *
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\Exception\InvalidSmsProviderException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class CegedimVortext implements IProvider {
	public const PROVIDER_ID = 'cegedim.cloud';

	/** @var IClient */
	private $client;

	/** @var CegedimVortextConfig */
	private $config;

	/**
	 * Url to communicate with Vortext API
	 *
	 * @var array
	 */
	private $endpoints = [
		'eb4'        => 'https://vortext.cloud.cegedim.com'
	];

	/**
	 * Array of the 3 needed parameters to connect to the API
	 * @var array
	 */
	private $attrs = [
		'user' => null,
		'password' => null,
		'endpoint' => null
	];


	public function __construct(IClientService $clientService,
								CegedimVortextConfig $config) {
		$this->client = $clientService->newClient();
		$this->config = $config;
	}

	/**
	 * @param string $identifier
	 * @param string $message
	 *
	 * @throws SmsTransmissionException
	 */
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();
		$endpoint = $config->getEndpoint();
		$this->attrs['user'] = $config->getUsername();
		$this->attrs['password'] = $config->getPassword();
		$this->attrs['endpoint'] = $config->getEndpoint();
		if (!isset($this->endpoints[$endpoint])) {
			throw new InvalidSmsProviderException("Endpoint $endpoint not found");
		}
		$this->attrs['endpoint'] = $this->endpoints[$endpoint];

		$content = [
			"message"=> $message,
			"phoneNumber"=> $identifier,
		];
		$body = json_encode($content);

		$response = $this->client->post(
			$this->attrs['endpoint']."/sms",[
				'json' => $content,
				'auth' => [$this->attrs['user'],$this->attrs['password']]
			]
		);
		$resultPostJob = json_decode($response->getBody(),true);

		if (strlen($resultPostJob["messageId"]) === 0) {
			throw new SmsTransmissionException("Bad receiver $identifier");
		}
	}

	/**
	 * @return CegedimVortextConfig
	 */
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
