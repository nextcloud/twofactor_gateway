<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Pascal Clémot <pascal.clemot@free.fr>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class PlaySMS implements IProvider {
	public const PROVIDER_ID = 'playsms';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private PlaySMSConfig $config,
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
						'app' => 'ws',
						'u' => $config->getUser(),
						'h' => $config->getPassword(),
						'op' => 'pv',
						'to' => $identifier,
						'msg' => $message,
					],
				]
			);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return PlaySMSConfig
	 */
	#[\Override]
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
