<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Mario Klug <mario.klug@sourcefactory.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

/**
 * @method string getApitoken()
 * @method static setApitoken(string $apitoken)
 */
class ClockworkSMS extends AProvider {
	public const SCHEMA = [
		'name' => 'ClockworkSMS',
		'fields' => [
			['field' => 'apitoken', 'prompot' => 'Please enter your clockworksms api token:'],
		]
	];
	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		try {
			$response = $this->client->get(
				'https://api.clockworksms.com/http/send.aspx',
				[
					'query' => [
						'key' => $this->getApiToken(),
						'to' => $identifier,
						'content' => $message,
					],
				]
			);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
