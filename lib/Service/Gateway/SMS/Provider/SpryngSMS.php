<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Ruben de Wit <ruben@iamit.nl>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class SpryngSMS implements IProvider {
	public const PROVIDER_ID = 'spryng';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private SpryngSMSConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();
		/** @var SpryngSMSConfig $providerConfig */
		try {
			$response = $this->client->post(
				'https://rest.spryngsms.com/v1/messages?with%5B%5D=recipients',
				[
					'headers' => [
						'Accept' => 'application/json',
						'Authorization' => 'Bearer ' . $config->getApiToken(),
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
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return SpryngSMSConfig
	 */
	#[\Override]
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
