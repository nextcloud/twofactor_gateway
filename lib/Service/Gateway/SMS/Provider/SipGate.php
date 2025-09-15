<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class SipGate implements IProvider {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
		public SipGateConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$tokenId = $this->config->getTokenId();
		$accessToken = $this->config->getAccessToken();
		$webSmsExtension = $this->config->getWebSmsExtension();

		try {
			$this->client->post('https://api.sipgate.com/v2/sessions/sms', [
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode("$tokenId:$accessToken"),
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
				],
				'json' => [
					'smsId' => $webSmsExtension,
					'message' => $message,
					'recipient' => $identifier,
					'sendAt' => null,
				],
			]);
		} catch (Exception $ex) {
			throw new MessageTransmissionException('SipGate Send Failed', $ex->getCode(), $ex);
		}
	}
}
