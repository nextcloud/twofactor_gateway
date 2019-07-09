<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

class GatewayApiSms implements IProvider {

	const PROVIDER_ID = 'gatewayapisms';

	/** @var IClient */
	private $client;

	/** @var GatewayApiSmsConfig */
	private $config;

	public function __construct(IClientService $clientService,
								GatewayApiSmsConfig $config) {
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
		$authtoken = $config->getAuthToken();
		$senderid = $config->getSenderId();
		try {
			$this->client->post("https://gatewayapi.com/rest/mtsms?token=$authtoken", [
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'json' => [
					"class" => "premium",
					"priority" => "VERY_URGENT",
					"destaddr" => "DISPLAY",
					"recipients" => [
						[ "msisdn" => "$identifier" ]
					],
					"sender" => "$senderid",
					"message" => "$message",
				],
			]);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return GatewayApiSmsConfig
	 */
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
