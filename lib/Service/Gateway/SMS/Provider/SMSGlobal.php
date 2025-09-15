<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Pascal Clémot <pascal.clemot@free.fr>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class SMSGlobal implements IProvider {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
		public SMSGlobalConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$to = str_replace('+', '', $identifier);

		try {
			$this->client->get(
				$this->config->getUrl(),
				[
					'query' => [
						'action' => 'sendsms',
						'user' => $this->config->getUser(),
						'password' => $this->config->getPassword(),
						'origin' => 'nextcloud',
						'from' => 'nextcloud',
						'to' => $to,
						'text' => $message,
						'clientcharset' => 'UTF-8',
						'detectcharset' => 1
					],
				]
			);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
