<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Jordan Bieder <jordan.bieder@geduld.fr>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\Drivers;

use Exception;
use OCA\TwoFactorGateway\Exception\InvalidProviderException;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\AProvider;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

/**
 * @method string getApplicationKey()
 * @method static setApplicationKey(string $applicationKey)
 * @method string getApplicationSecret()
 * @method static setApplicationSecret(string $applicationSecret)
 * @method string getConsumerKey()
 * @method static setConsumerKey(string $consumerKey)
 * @method string getEndpoint()
 * @method static setEndpoint(string $endpoint)
 * @method string getAccount()
 * @method static setAccount(string $account)
 * @method string getSender()
 * @method static setSender(string $sender)
 */
class Ovh extends AProvider {
	private IClient $client;

	/**
	 * Url to communicate with Ovh API
	 */
	private array $endpoints = [
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
	 */
	private array $attrs = [
		'AK' => null,
		'AS' => null,
		'CK' => null,
		'endpoint' => null,
		'timedelta' => null
	];


	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	public function createSettings(): Settings {
		return new Settings(
			id: 'ovh',
			name: 'OVH',
			fields: [
				new FieldDefinition(
					field: 'endpoint',
					prompt: 'Please enter the endpoint (ovh-eu, ovh-us, ovh-ca, soyoustart-eu, soyoustart-ca, kimsufi-eu, kimsufi-ca, runabove-ca):',
				),
				new FieldDefinition(
					field: 'application_key',
					prompt: 'Please enter your application key:',
				),
				new FieldDefinition(
					field: 'application_secret',
					prompt: 'Please enter your application secret:',
				),
				new FieldDefinition(
					field: 'consumer_key',
					prompt: 'Please enter your consumer key:',
				),
				new FieldDefinition(
					field: 'account',
					prompt: 'Please enter your account (sms-*****):',
				),
				new FieldDefinition(
					field: 'sender',
					prompt: 'Please enter your sender:',
				),
			]
		);
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$endpoint = $this->getEndpoint();
		$sender = $this->getSender();
		$smsAccount = $this->getAccount();

		$this->attrs['AK'] = $this->getApplicationKey();
		$this->attrs['AS'] = $this->getApplicationSecret();
		$this->attrs['CK'] = $this->getConsumerKey();
		if (!isset($this->endpoints[$endpoint])) {
			throw new InvalidProviderException("Endpoint $endpoint not found");
		}
		$this->attrs['endpoint'] = $this->endpoints[$endpoint];

		$this->getTimeDelta();

		$header = $this->getHeader('GET', $this->attrs['endpoint'] . '/sms');
		$response = $this->client->get($this->attrs['endpoint'] . '/sms', [
			'headers' => $header,
		]);
		$body = (string)$response->getBody();
		$smsServices = json_decode($body, true);

		$smsAccountFound = false;
		foreach ($smsServices as $smsService) {
			if ($smsService === $smsAccount) {
				$smsAccountFound = true;
				break;
			}
		}
		if ($smsAccountFound === false) {
			throw new InvalidProviderException("SMS account $smsAccount not found");
		}
		$content = [
			'charset' => 'UTF-8',
			'message' => $message,
			'noStopClause' => true,
			'priority' => 'high',
			'receivers' => [ $identifier ],
			'senderForResponse' => false,
			'sender' => $sender,
			'validityPeriod' => 3600
		];
		$body = json_encode($content);

		$header = $this->getHeader('POST', $this->attrs['endpoint'] . "/sms/$smsAccount/jobs", $body);
		$response = $this->client->post($this->attrs['endpoint'] . "/sms/$smsAccount/jobs", [
			'headers' => $header,
			'json' => $content,
		]);
		$body = (string)$response->getBody();
		$resultPostJob = json_decode($body, true);

		if (count($resultPostJob['validReceivers']) === 0) {
			throw new MessageTransmissionException("Bad receiver $identifier");
		}
	}

	/**
	 * Compute time delta between this server and OVH endpoint
	 *
	 * @throws InvalidProviderException
	 */
	private function getTimeDelta(): void {
		if (!isset($this->attrs['timedelta'])) {
			if (!isset($this->attrs['endpoint'])) {
				throw new InvalidProviderException('Need to set the endpoint');
			}
			try {
				$response = $this->client->get($this->attrs['endpoint'] . '/auth/time');
				$serverTimestamp = (int)$response->getBody();
				$this->attrs['timedelta'] = $serverTimestamp - time();
			} catch (Exception $ex) {
				throw new InvalidProviderException('Unable to calculate time delta:' . $ex->getMessage());
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
		$prehash = $this->attrs['AS'] . '+' . $this->attrs['CK'] . '+' . $method . '+' . $query . '+' . $body . '+' . $timestamp;
		$header = [
			'Content-Type' => 'application/json; charset=utf-8',
			'X-Ovh-Application' => $this->attrs['AK'],
			'X-Ovh-Timestamp' => $timestamp,
			'X-Ovh-Signature' => '$1$' . sha1($prehash),
			'X-Ovh-Consumer' => $this->attrs['CK'],
		];
		return $header;
	}
}
