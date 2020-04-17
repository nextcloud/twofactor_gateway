<?php

declare(strict_types=1);

/**
 * @author Ruben de Wit <ruben@iamit.nl>
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

class SpryngSMS implements IProvider {
	public const PROVIDER_ID = 'spryng';

	/** @var IClient */
	private $client;

	/** @var SpryngSMSConfig */
	private $config;

	public function __construct(IClientService $clientService,
								SpryngSMSConfig $config) {
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
		/** @var SpryngSMSConfig $providerConfig */
		try {
			$response = $this->client->post(
				'https://rest.spryngsms.com/v1/messages?with%5B%5D=recipients',
				[
					'headers' => [
						'Accept' => 'application/json',
						'Authorization' => 'Bearer ' . $config->getApiToken(),
						'Content-Type' => 'application/json',
					],
					'json' => [
						'body' => $message,
						'encoding' => 'plain',
						'originator' => 'Nextcloud',
						'recipients' => [$identifier],
						'route' => '1',
					],
				]
			);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return SpryngSMSConfig
	 */
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
