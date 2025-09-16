<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Pascal Clémot <pascal.clemot@free.fr>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

/**
 * @method string getUrl()
 * @method static setUrl(string $url)
 * @method string getUser()
 * @method static setUser(string $user)
 * @method string getPassword()
 * @method static setPassword(string $password)
 */
class PlaySMS extends AProvider {
	public const SCHEMA = [
		'id' => 'playsms',
		'name' => 'PlaySMS',
		'fields' => [
			['field' => 'url',      'prompt' => 'Please enter your PlaySMS URL:'],
			['field' => 'user',     'prompt' => 'Please enter your PlaySMS username:'],
			['field' => 'password', 'prompt' => 'Please enter your PlaySMS password:'],
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
		try {
			$this->client->get(
				$this->getUrl(),
				[
					'query' => [
						'app' => 'ws',
						'u' => $this->getUser(),
						'h' => $this->getPassword(),
						'op' => 'pv',
						'to' => $identifier,
						'msg' => $message,
					],
				]
			);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
