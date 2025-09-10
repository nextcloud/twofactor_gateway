<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Kim Syversen <kim.syversen@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class PuzzelSMS implements IProvider {
	public const PROVIDER_ID = 'puzzelsms';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private PuzzelSMSConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();

		try {
			$this->client->get(
				$config->getUrl(),
				[
					'query' => [
						'username' => $config->getUser(),
						'password' => $config->getPassword(),
						'message[0].recipient' => '+' . $identifier,
						'message[0].content' => $message,
						'serviceId' => $config->getServiceId(),
					],
				]
			);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return PuzzelSMSConfig
	 */
	#[\Override]
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
