<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Fabian Zihlmann <fabian.zihlmann@mybica.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class EcallSMS implements IProvider {
	public const PROVIDER_ID = 'ecallsms';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private EcallSMSConfig $config,
	) {
		$this->client = $clientService->newClient();
		$this->config = $config;
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();
		$user = $config->getUser();
		$password = $config->getPassword();
		$senderId = $config->getSenderId();
		try {
			$this->client->get('https://url.ecall.ch/api/sms', [
				'query' => [
					'username' => $user,
					'password' => $password,
					'Callback' => $senderId,
					'address' => $identifier,
					'message' => $message,
				],
			]);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return EcallSMSConfig
	 */
	#[\Override]
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
