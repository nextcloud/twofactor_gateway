<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 André Matthies <a.matthies@sms77.io>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class Sms77Io implements IProvider {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
		public Sms77IoConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$apiKey = $this->config->getApiKey();
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
