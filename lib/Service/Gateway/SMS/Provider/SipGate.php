<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class SipGate implements IProvider {
	public const PROVIDER_ID = 'sipgate';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private SipGateConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();
		$tokenId = $config->getTokenId();
		$accessToken = $config->getAccessToken();
		$webSmsExtension = $config->getWebSmsExtension();

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
			throw new SmsTransmissionException('SipGate Send Failed', $ex->getCode(), $ex);
		}
	}

	/**
	 * @return SipGateConfig
	 */
	#[\Override]
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
