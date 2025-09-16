<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Kim Syversen <kim.syversen@gmail.com>
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
 * @method string getServiceid()
 * @method static setServiceid(string $serviceid)
 */
class PuzzelSMS extends AProvider {
	public const SCHEMA = [
		'id' => 'puzzel',
		'name' => 'Puzzel SMS',
		'fields' => [
			['field' => 'url',       'prompt' => 'Please enter your PuzzelSMS URL:'],
			['field' => 'user',      'prompt' => 'Please enter your PuzzelSMS username:'],
			['field' => 'password',  'prompt' => 'Please enter your PuzzelSMS password:'],
			['field' => 'serviceid', 'prompt' => 'Please enter your PuzzelSMS service ID:'],
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
						'username' => $this->getUser(),
						'password' => $this->getPassword(),
						'message[0].recipient' => '+' . $identifier,
						'message[0].content' => $message,
						'serviceId' => $this->getServiceid(),
					],
				]
			);
		} catch (Exception $ex) {
			throw new MessageTransmissionException();
		}
	}
}
