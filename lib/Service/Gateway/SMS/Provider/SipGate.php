<?php

declare(strict_types=1);

/**
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
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

class SipGate implements IProvider {
	public const PROVIDER_ID = 'sipgate';

	/** @var IClient */
	private $client;

	/** @var WebSmsConfig */
	private $config;

	public function __construct(IClientService $clientService,
								SipGateConfig $config) {
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
		$tokenId = $config->getTokenId();
		$accessToken = $config->getAccessToken();
		$webSmsExtension = $config->getWebSmsExtension();

		try {
			$this->client->post('https://api.sipgate.com/v2/sessions/sms', [
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode("$tokenId:$accessToken"),
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
				],
				'json' => [
					"smsId" => $webSmsExtension,
					"message" => $message,
					"recipient" => $identifier,
					"sendAt" => null,
				],
			]);
		} catch (Exception $ex) {
			throw new SmsTransmissionException('SipGate Send Failed', $ex->getCode(), $ex);
		}
	}

	/**
	 * @return SipGateConfig
	 */
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
