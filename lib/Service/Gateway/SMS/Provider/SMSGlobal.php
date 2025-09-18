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

/**
 * @method string getUrl()
 * @method static setUrl(string $url)
 * @method string getUser()
 * @method static setUser(string $user)
 * @method string getPassword()
 * @method static setPassword(string $password)
 */
class SMSGlobal extends AProvider {
	public const SCHEMA = [
		'name' => 'SMSGlobal',
		'fields' => [
			['field' => 'url',      'prompt' => 'Please enter your SMSGlobal http-api:', 'default' => 'https://api.smsglobal.com/http-api.php'],
			['field' => 'user',     'prompt' => 'Please enter your SMSGlobal username (for http-api):'],
			['field' => 'password', 'prompt' => 'Please enter your SMSGlobal password (for http-api):'],
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
		$to = str_replace('+', '', $identifier);

		try {
			$this->client->get(
				$this->getUrl(),
				[
					'query' => [
						'action' => 'sendsms',
						'user' => $this->getUser(),
						'password' => $this->getPassword(),
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
