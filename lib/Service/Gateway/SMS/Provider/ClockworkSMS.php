<?php

declare(strict_types=1);

/**
 * @author Mario Klug <mario.klug@sourcefactory.at>
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

class ClockworkSMS implements IProvider {
	public const PROVIDER_ID = 'clockworksms';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private ClockworkSMSConfig $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();

		try {
			$response = $this->client->get(
				'https://api.clockworksms.com/http/send.aspx',
				[
					'query' => [
						'key' => $config->getApiToken(),
						'to' => $identifier,
						'content' => $message,
					],
				]
			);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return ClockworkSMSConfig
	 */
	#[\Override]
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
