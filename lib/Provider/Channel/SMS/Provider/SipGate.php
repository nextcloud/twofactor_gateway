<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

/**
 * @method string getTokenId()
 * @method static setTokenId(string $tokenId)
 * @method string getAccessToken()
 * @method static setAccessToken(string $accessToken)
 * @method string getWebSmsExtension()
 * @method static setWebSmsExtension(string $webSmsExtension)
 */
class SipGate extends AProvider {
	public const SCHEMA = [
		'name' => 'SipGate',
		'fields' => [
			['field' => 'token_id',        'prompt' => 'Please enter your sipgate token-id:'],
			['field' => 'access_token',    'prompt' => 'Please enter your sipgate access token:'],
			['field' => 'web_sms_extension','prompt' => 'Please enter your sipgate web-sms extension:'],
		],
	];
	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$tokenId = $this->getTokenId();
		$accessToken = $this->getAccessToken();
		$webSmsExtension = $this->getWebSmsExtension();

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
