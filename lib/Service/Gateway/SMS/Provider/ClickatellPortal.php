<?php

declare(strict_types=1);

/**
 * @author Christian Schrötter <cs@fnx.li>
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

class ClickatellPortal implements IProvider {
	public const PROVIDER_ID = 'clickatellportal';

	/** @var IClient */
	private $client;

	/** @var ClickatellPortalConfig */
	private $config;

	public function __construct(IClientService $clientService,
								ClickatellPortalConfig $config) {
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
			$from = $config->getFromNumber();
			$from = !is_null($from) ? sprintf('&from=%s', urlencode($from)) : '';
			$response = $this->client->get(vsprintf('https://platform.clickatell.com/messages/http/send?apiKey=%s&to=%s&content=%s%s', [
				urlencode($config->getApiKey()),
				urlencode($identifier),
				urlencode($message),
				$from,
			]));
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}

		if ($response->getStatusCode() !== 202) {
			throw new SmsTransmissionException($response->getBody());
		}
	}

	/**
	 * @return ClickatellPortalConfig
	 */
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
