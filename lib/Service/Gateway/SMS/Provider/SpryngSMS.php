<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Ruben de Wit <ruben@iamit.nl>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

/**
 * @method string getApitoken()
 * @method static setApitoken(string $apitoken)
 */
class SpryngSMS extends AProvider {
	public const SCHEMA = [
		'name' => 'Spryng',
		'fields' => [
			['field' => 'apitoken', 'prompt' => 'Please enter your Spryng api token:'],
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
		try {
			$this->client->post(
				'https://rest.spryngsms.com/v1/messages?with%5B%5D=recipients',
				[
					'headers' => [
						'Accept' => 'application/json',
						'Authorization' => 'Bearer ' . $this->getApitoken(),
						'Content-Type' => 'application/json',
					],
					'json' => [
						'body' => $message,
						'encoding' => 'plain',
						'originator' => 'Nextcloud',
						'recipients' => [$identifier],
						'route' => '1',
					],
				]
			);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
