<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christian Schrötter <cs@fnx.li>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class ClickatellCentral implements IProvider {
	public const PROVIDER_ID = 'clickatellcentral';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private ClickatellCentralConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();
		try {
			$response = $this->client->get(vsprintf('https://api.clickatell.com/http/sendmsg?user=%s&password=%s&api_id=%u&to=%s&text=%s', [
				urlencode($config->getUser()),
				urlencode($config->getPassword()),
				$config->getApi(),
				urlencode($identifier),
				urlencode($message),
			]));
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}

		if ($response->getStatusCode() !== 200 || substr($response->getBody(), 0, 4) !== 'ID: ') {
			throw new SmsTransmissionException($response->getBody());
		}
	}

	/**
	 * @return ClickatellCentralConfig
	 */
	#[\Override]
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
