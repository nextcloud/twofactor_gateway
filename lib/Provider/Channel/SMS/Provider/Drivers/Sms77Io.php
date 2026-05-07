<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 André Matthies <a.matthies@sms77.io>
 * SPDX-FileCopyrightText: 2026 seven communications GmbH & Co. KG <support@seven.io>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\Drivers;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\AProvider;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

/**
 * Driver for the seven (formerly sms77.io) SMS gateway.
 *
 * The settings id is kept as `sms77io` to preserve existing configurations
 * after the company rebranded to seven.
 *
 * @method string getApiKey()
 * @method self setApiKey(string $apiKey)
 */
class Sms77Io extends AProvider {
	private const ENDPOINT = 'https://gateway.seven.io/api/sms';
	private const SUCCESS_CODE = '100';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	public function createSettings(): Settings {
		return new Settings(
			id: 'sms77io',
			name: 'seven (formerly sms77.io)',
			fields: [
				new FieldDefinition(
					field: 'api_key',
					prompt: 'Please enter your seven API key:',
					helper: 'Create an API key at https://dashboard.seven.io/developer',
				),
			]
		);
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		try {
			$response = $this->client->post(self::ENDPOINT, [
				'headers' => [
					'Accept' => 'application/json',
					'X-Api-Key' => $this->getApiKey(),
				],
				'body' => [
					'to' => $identifier,
					'text' => $message,
					'sendWith' => 'nextcloud',
				],
			]);
		} catch (Exception $ex) {
			throw new MessageTransmissionException(
				'Failed to reach seven SMS gateway: ' . $ex->getMessage(),
				0,
				$ex,
			);
		}

		$this->assertDispatched((string)$response->getBody());
	}

	/**
	 * The seven gateway answers HTTP 200 even on logical errors. The top-level
	 * `success` only confirms the request was accepted; per-recipient delivery
	 * is reported in `messages[].success` plus an `error`/`error_text` pair.
	 * Both layers must be checked.
	 *
	 * @see https://docs.seven.io/rest-api/endpoints/sms
	 */
	private function assertDispatched(string $body): void {
		$decoded = json_decode($body, true);

		if (!is_array($decoded)) {
			// Plain-text fallback: "<code>\n<msg-id>".
			$code = trim(strtok($body, "\n") ?: '');
			if ($code !== self::SUCCESS_CODE) {
				throw new MessageTransmissionException(
					'seven SMS gateway returned status ' . ($code !== '' ? $code : 'unknown'),
				);
			}
			return;
		}

		$code = (string)($decoded['success'] ?? '');
		if ($code !== self::SUCCESS_CODE) {
			throw new MessageTransmissionException(
				'seven SMS gateway returned status ' . ($code !== '' ? $code : 'unknown'),
			);
		}

		foreach ($decoded['messages'] ?? [] as $message) {
			if (!is_array($message) || ($message['success'] ?? true) !== false) {
				continue;
			}
			$reason = (string)($message['error_text'] ?? $message['error'] ?? 'unknown error');
			throw new MessageTransmissionException(
				'seven SMS gateway rejected recipient: ' . $reason,
			);
		}
	}
}
