<?php

declare(strict_types=1);

/**
 * @author Paweł Kuffel <pawel@kuffel.io>
 *
 * Nextcloud - Two-factor Gateway for SerwerSMS.pl
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

class SerwerSMS implements IProvider {
	public const PROVIDER_ID = 'serwersms';

	/** @var IClient */
	private $client;

	/** @var SerwerSMSConfig */
	private $config;

	public function __construct(IClientService $clientService,
							SerwerSMSConfig $config) {
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
		$login = $config->getLogin();
		$password = $config->getPassword();
		$sender = $config->getSender();
		try {
			$response = $this->client->post('https://api2.serwersms.pl/messages/send_sms', [
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'json' => [
					'username' => $login,
					'password' => $password,
					'phone' => $identifier,
					'text' => $message,
					'sender' => $sender,
				],
			]);

			$responseData = json_decode($response->getBody(), true);

			if ($responseData['success'] !== true) {
				throw new SmsTransmissionException();
			}
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return SerwerSMSConfig
	 */
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
