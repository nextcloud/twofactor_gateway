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

class ClickatellCentral implements IProvider {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
		public ClickatellCentralConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		try {
			$response = $this->client->get(vsprintf('https://api.clickatell.com/http/sendmsg?user=%s&password=%s&api_id=%u&to=%s&text=%s', [
				urlencode($this->config->getUser()),
				urlencode($this->config->getPassword()),
				$this->config->getApi(),
				urlencode($identifier),
				urlencode($message),
			]));
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}

		if ($response->getStatusCode() !== 200 || substr($response->getBody(), 0, 4) !== 'ID: ') {
			throw new MessageTransmissionException($response->getBody());
		}
	}
}
