<?php

declare(strict_types=1);

/**
 * @author Kim Syversen <kim.syversen@gmail.com>
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

class PuzzelSMS implements IProvider {
	public const PROVIDER_ID = 'puzzelsms';

	/** @var IClient */
	private $client;

	/** @var PuzzelConfig */
	private $config;

	public function __construct(IClientService $clientService,
								PuzzelSMSConfig $config) {
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

		try {
			$this->client->get(
				$config->getUrl(),
				[
					'query' => [
						'username' => $config->getUser(),
						'password' => $config->getPassword(),
						"message[0].recipient" => "+".$identifier,
						"message[0].content" => $message,
						'serviceId' => $config->getServiceId(),
					],
				]
			);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return PuzzelConfig
	 */
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
