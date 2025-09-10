<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Pascal Clémot <pascal.clemot@free.fr>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class SMSGlobal implements IProvider {
	public const PROVIDER_ID = 'smsglobal';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private SMSGlobalConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();
		$to = str_replace('+', '', $identifier);

		try {
			$this->client->get(
				$config->getUrl(),
				[
					'query' => [
						'action' => 'sendsms',
						'user' => $config->getUser(),
						'password' => $config->getPassword(),
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
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return SMSGlobalConfig
	 */
	#[\Override]
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
