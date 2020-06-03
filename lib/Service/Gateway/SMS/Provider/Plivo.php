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
use \OCP\ILogger;

class Plivo implements IProvider {
	public const PROVIDER_ID = 'plivo';

	/** @var IClient */
	private $client;

	/** @var PlivoConfig */
	private $config;
	
	private $logger;

	public function __construct(IClientService $clientService,
							PlivoConfig $config, ILogger $logger) {
		$this->client = $clientService->newClient();
		$this->config = $config;
		$this->logger = $logger;
	}
	
	/**
	 * @param string $identifier
	 * @param string $message
	 *
	 * @throws SmsTransmissionException
	 */
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();
		$authToken = $config->getValue($config::AUTH_TOKEN_KEY);
		$authID = $config->getValue($config::AUTH_ID_KEY);
		$srcNumber = $config->getValue($config::SRC_NUMBER_KEY);
		$callbackUrl = $config->getValue($config::CALLBACK_URL);
		
		$apiParams = [
					'body' => json_encode([
						'dst' => $identifier,
						'src' => $srcNumber,
						'text' => $message
					],JSON_FORCE_OBJECT),
					'headers' => [
						'Content-Type' => "application/json",
						'Authorization' => "Basic " . base64_encode($authID.':'.$authToken)
					],
					'debug' => true
				];
		
		try {
			$this->client->post("https://api.plivo.com/v1/Account/$authID/Message/", $apiParams);
		} catch (Exception $ex) {
			$this->logger->error("api call: https://api.plivo.com/v1/Account/$authID/Message/" .print_r($apiParams,true));
			$this->logger->error($ex->getMessage());
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
