<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Mario Klug <mario.klug@sourcefactory.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class ClockworkSMS implements IProvider {
	public const PROVIDER_ID = 'clockworksms';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		public ClockworkSMSConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		try {
			$response = $this->client->get(
				'https://api.clockworksms.com/http/send.aspx',
				[
					'query' => [
						'key' => $this->config->getApiToken(),
						'to' => $identifier,
						'content' => $message,
					],
				]
			);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
