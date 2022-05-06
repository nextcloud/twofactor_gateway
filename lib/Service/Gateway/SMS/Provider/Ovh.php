<?php

declare(strict_types=1);

/**
 * @author Jordan Bieder <jordan.bieder@geduld.fr>
 *
 * Nextcloud - Two-factor Gateway for Ovh
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

class Ovh implements IProvider {
	public const PROVIDER_ID = 'ovh';

	/** @var IClient */
	private $client;

	/** @var OvhConfig */
	private $config;

	/**
	 * Url to communicate with Ovh API
	 *
	 * @var array
	 */
	private $endpoints = [
		'ovh-eu' => 'https://api.ovh.com/1.0',
		'ovh-us' => 'https://api.us.ovhcloud.com/1.0',
		'ovh-ca' => 'https://ca.api.ovh.com/1.0',
		'kimsufi-eu' => 'https://eu.api.kimsufi.com/1.0',
		'kimsufi-ca' => 'https://ca.api.kimsufi.com/1.0',
		'soyoustart-eu' => 'https://eu.api.soyoustart.com/1.0',
		'soyoustart-ca' => 'https://ca.api.soyoustart.com/1.0',
		'runabove-ca' => 'https://api.runabove.com/1.0',
	];

	/**
	 * Array of the 4 needed parameters to connect to the API
	 * @var array
	 */
	private $attrs = [
		'AK' => null,
		'AS' => null,
		'CK' => null,
		'endpoint' => null,
		'timedelta' => null
	];


	public function __construct(IClientService $clientService,
								OvhConfig $config) {
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
		$sender = $config->getSender();
		$smsAccount = $config->getAccount();

		$this->attrs['AK'] = $config->getApplicationKey();
		$this->attrs['AS'] = $config->getApplicationSecret();
		$this->attrs['CK'] = $config->getConsumerKey();
		if (!isset($this->endpoints[$endpoint])) {
			throw new InvalidSmsProviderException("Endpoint $endpoint not found");
		}
		$this->attrs['endpoint'] = $this->endpoints[$endpoint];

		$this->getTimeDelta();

		$header = $this->getHeader('GET', $this->attrs['endpoint'].'/sms');
		$response = $this->client->get($this->attrs['endpoint'].'/sms', [
			'headers' => $header,
		]);
		$smsServices = json_decode($response->getBody(), true);

		$smsAccountFound = false;
		foreach ($smsServices as $smsService) {
			if ($smsService === $smsAccount) {
				$smsAccountFound = true;
				break;
			}
		}
		if ($smsAccountFound === false) {
			throw new InvalidSmsProviderException("SMS account $smsAccount not found");
		}
		$content = [
			"charset" => "UTF-8",
			"message" => $message,
			"noStopClause" => true,
			"priority" => "high",
			"receivers" => [ $identifier ],
			"senderForResponse" => false,
			"sender" => $sender,
			"validityPeriod" => 3600
		];
		$body = json_encode($content);

		$header = $this->getHeader('POST', $this->attrs['endpoint']."/sms/$smsAccount/jobs", $body);
		$response = $this->client->post($this->attrs['endpoint']."/sms/$smsAccount/jobs", [
			'headers' => $header,
			'json' => $content,
		]);
		$resultPostJob = json_decode($response->getBody(), true);

		if (count($resultPostJob["validReceivers"]) === 0) {
			throw new SmsTransmissionException("Bad receiver $identifier");
		}
	}

	/**
	 * @return OvhConfig
	 */
	public function getConfig(): IProviderConfig {
		return $this->config;
	}

	/**
	 * Compute time delta between this server and OVH endpoint
	 * @throws InvalidSmsProviderException
	 */
	private function getTimeDelta() {
		if (!isset($this->attrs['timedelta'])) {
			if (!isset($this->attrs['endpoint'])) {
				throw new InvalidSmsProviderException('Need to set the endpoint');
			}
			try {
				$response = $this->client->get($this->attrs['endpoint'].'/auth/time');
				$serverTimestamp = (int)$response->getBody();
				$this->attrs['timedelta'] = $serverTimestamp - time();
			} catch (Exception $ex) {
				throw new InvalidSmsProviderException('Unable to calculate time delta:'.$ex->getMessage());
			}
		}
	}

	/**
	 * Make header for Ovh
	 * @param string $method The methode use for the query : GET, POST, PUT, DELETE
	 * @param string $query The fulle URI for the query: https://eu.api.ovh.com/1.0/......
	 * @param string $body JSON encoded body content for the POST request
	 * @return array $header Contains the data for the request need by OVH
	 */
	private function getHeader($method, $query, $body = '') {
		$timestamp = time() + $this->attrs['timedelta'];
		$prehash = $this->attrs['AS'].'+'.$this->attrs['CK'].'+'.$method.'+'.$query.'+'.$body.'+'.$timestamp;
		$header = [
			'Content-Type' => 'application/json; charset=utf-8',
			'X-Ovh-Application' => $this->attrs['AK'],
			'X-Ovh-Timestamp' => $timestamp,
			'X-Ovh-Signature' => '$1$'.sha1($prehash),
			'X-Ovh-Consumer' => $this->attrs['CK'],
		];
		return $header;
	}
}
