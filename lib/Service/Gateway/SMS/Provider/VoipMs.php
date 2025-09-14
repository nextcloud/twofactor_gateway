<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Francois Blackburn <blackburnfrancois@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class VoipMs implements IProvider {
	public const PROVIDER_ID = 'voipms';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		public VoipMsConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$user = $this->config->getUser();
		$password = $this->config->getPassword();
		$did = $this->config->getDid();
		try {
			$this->client->get('https://voip.ms/api/v1/rest.php', [
				'query' => [
					'api_username' => $user,
					'api_password' => $password,
					'method' => 'sendSMS',
					'did' => $did,
					'dst' => $identifier,
					'message' => $message,
				],
			]);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}
}
