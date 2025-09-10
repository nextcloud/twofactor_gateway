<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Henning Bopp <henning.bopp@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class ClickSend implements IProvider {
	public const PROVIDER_ID = 'clicksend';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private ClickSendConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();
		$apiKey = $config->getApiKey();
		$username = $config->getUser();
		try {
			$this->client->get('https://api-mapper.clicksend.com/http/v2/send.php', [
				'query' => [
					'method' => 'http',
					'username' => $username,
					'key' => $apiKey,
					'to' => $identifier,
					'message' => $message,
					'senderid' => 'nextcloud'
				],
			]);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return ClickSendConfig
	 */
	#[\Override]
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
