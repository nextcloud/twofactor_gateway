<?php

declare(strict_types=1);

/**
 * @author Daif Alazmi <daif@daif.net>
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

class CustomSMS implements IProvider {
	public const PROVIDER_ID = 'customsms';

	/** @var IClient */
	private $client;

	/** @var CustomSMSConfig */
	private $config;

	public function __construct(IClientService $clientService,
								CustomSMSConfig $config) {
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

			if(strtolower($config->getMethod()) == 'get')
			{
				$options = [
					'query'=> [
						$config->getIdentifier()=>$identifier,
						$config->getMessage()=>$message,
					]
				];
				parse_str($config->getHeaders(), $headers);
				if(!empty($headers))
				{
					$options['headers'] = $headers;
				}
				parse_str($config->getParameters(), $parameters);
				if(!empty($parameters))
				{
					$options['query'] = $options['query'] + $parameters;
				}
				$response = $this->client->get($config->getUrl(),$options);
			}

			if(strtolower($config->getMethod()) == 'post')
			{
				$options = [
					'body'=> [
						$config->getIdentifier()=>$identifier,
						$config->getMessage()=>$message,
					]
				];
				parse_str($config->getHeaders(), $headers);
				if(!empty($headers))
				{
					$options['headers'] = $headers;
				}
				parse_str($config->getParameters(), $parameters);
				if(!empty($parameters))
				{
					$options['body'] = $options['body'] + $parameters;
				}
				$this->client->post($config->getUrl(),$options);
			}

		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return CustomSMSConfig
	 */
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
