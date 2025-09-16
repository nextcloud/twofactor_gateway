<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christian Schrötter <cs@fnx.li>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

/**
 * @method string getApikey()
 * @method static setApikey(string $apikey)
 *
 * @method string getFrom()
 * @method static setFrom(string $from)
 */
class ClickatellPortal extends AProvider {
	public const SCHEMA = [
		'id' => 'clickatell_portal',
		'name' => 'Clickatell Portal',
		'fields' => [
			['field' => 'apikey', 'prompt' => 'Please enter your portal.clickatell.com API-Key:'],
			['field' => 'from',   'prompt' => 'Please enter your sender number for two-way messaging (empty = one-way): ', 'optional' => true],
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
			$from = $this->getFrom();
			$from = !is_null($from) ? sprintf('&from=%s', urlencode($from)) : '';
			$response = $this->client->get(vsprintf('https://platform.clickatell.com/messages/http/send?apiKey=%s&to=%s&content=%s%s', [
				urlencode($this->getApikey()),
				urlencode($identifier),
				urlencode($message),
				$from,
			]));
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}

		if ($response->getStatusCode() !== 202) {
			throw new MessageTransmissionException($response->getBody());
		}
	}
}
