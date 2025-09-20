<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 André Matthies <a.matthies@sms77.io>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

/**
 * @method string getApiKey()
 * @method static setApiKey(string $apiKey)
 */
class Sms77Io extends AProvider {
	public const SCHEMA = [
		'name' => 'sms77.io',
		'fields' => [
			['field' => 'api_key', 'prompt' => 'Please enter your sms77.io API key:'],
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
		try {
			$this->client->get('https://gateway.sms77.io/api/sms', [
				'query' => [
					'p' => $apiKey,
					'to' => $identifier,
					'text' => $message,
					'sendWith' => 'nextcloud'
				],
			]);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
