<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 André Matthies <a.matthies@sms77.io>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class Sms77Io implements IProvider {
	public const PROVIDER_ID = 'sms77io';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private Sms77IoConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();
		$apiKey = $config->getApiKey();
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
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return Sms77IoConfig
	 */
	#[\Override]
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
