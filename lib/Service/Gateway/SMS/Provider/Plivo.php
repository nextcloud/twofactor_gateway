<?php

declare(strict_types=1);

/**
 * @author Chris Jenkins <chris.j@guruinventions.com>
 *
 * Plivo - Config for Two-factor Gateway for Plivo
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

class Plivo implements IProvider {
	public const PROVIDER_ID = 'plivo';

	/** @var IClient */
	private $client;

	/** @var PlivoConfig */
	private $config;

	public function __construct(IClientService $clientService,
							PlivoConfig $config) {
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
		$authToken = $config->getAuthToken();
		$authID = $config->getAuthID();
		$srcNumber = $config->getSrcNumber();
		$callbackUrl = $config->getCallbackUrl();
		
		try {
			$this->client->get("https://api.plivo.com/v1/Account/$authID/Message/", [
				'body' => json_encode([
							'to' => $identifier,
							'src' => $srcNumber,
							'txt' => $message,
							'url' => $callbackUrl
						],JSON_FORCE_OBJECT),
				'headers' => [
					'Content-Type' => "application/json",
					'Authorization' => "Basic " . base64_encode($authID.':'.$authToken)
				]
			]);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}
	
	/**
	 * @return PlivoConfig
	 */
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
