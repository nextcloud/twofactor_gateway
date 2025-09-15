<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Francois Blackburn <blackburnfrancois@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class Voipbuster implements IProvider {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
		public VoipbusterConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$user = $this->config->getApiUser();
		$password = $this->config->getApiPassword();
		$did = $this->config->getDid();
		try {
			$this->client->get('https://www.voipbuster.com/myaccount/sendsms.php', [
				'query' => [
					'username' => $user,
					'password' => $password,
					'from' => $did,
					'to' => $identifier,
					'text' => $message,
				],
			]);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
