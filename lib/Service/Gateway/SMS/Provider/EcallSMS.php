<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Fabian Zihlmann <fabian.zihlmann@mybica.ch>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

/**
 * @method string getUsername()
 * @method static setUsername(string $username)
 * @method string getPassword()
 * @method static setPassword(string $password)
 * @method string getSenderid()
 * @method static setSenderid(string $senderid)
 */
class EcallSMS extends AProvider {
	public const SCHEMA = [
		'name' => 'EcallSMS',
		'fields' => [
			['field' => 'user',      'prompt' => 'Please enter your eCall.ch username:'],
			['field' => 'password',  'prompt' => 'Please enter your eCall.ch password:'],
			['field' => 'sender_id', 'prompt' => 'Please enter your eCall.ch sender ID:'],
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
		$user = $this->getUsername();
		$password = $this->getPassword();
		$senderId = $this->getSenderId();
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
			throw new MessageTransmissionException();
		}
	}
}
