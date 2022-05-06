<?php

declare(strict_types=1);

/**
 * @author Bosdla
 *
 * Nextcloud - Two-factor Gateway
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class SMSGlobal implements IProvider {
	public const PROVIDER_ID = 'smsglobal';

	/** @var IClient */
	private $client;

	/** @var SMSGlobalConfig */
	private $config;

	public function __construct(IClientService $clientService,
								SMSGlobalConfig $config) {
		$this->client = $clientService->newClient();
		$this->config = $config;
	}

	/**
	 * @param string $identifier
	 * @param string $message
	 *
	 * @throws SmsTransmissionException
	 */
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();
		$to = str_replace("+", "", $identifier);

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
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
