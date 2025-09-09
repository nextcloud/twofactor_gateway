<?php

declare(strict_types=1);

/**
 * @author Marcin Kot <kodek11@gmail.com>
 *
 * Nextcloud - Two-factor Gateway for SMSApi.com
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

class SMSApi implements IProvider {
	public const PROVIDER_ID = 'smsapi.com';

	/** @var IClient */
	private $client;

	/** @var SMSApiConfig */
	private $config;

	public function __construct(IClientService $clientService,
		SMSApiConfig $config) {
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
		$sender = $config->getSender();
		$token = $config->getToken();
		$url = 'https://api.smsapi.com/sms.do';

		$params = [
			'to' => $identifier,         //destination number
			'from' => $sender,             //sendername made in https://ssl.smsapi.com/sms_settings/sendernames
			'message' => $message,    		//message content
			'format' => 'json',           	//get response in json format
		];

		try {
			static $content;

			$c = curl_init();
			curl_setopt($c, CURLOPT_URL, $url);
			curl_setopt($c, CURLOPT_POST, true);
			curl_setopt($c, CURLOPT_POSTFIELDS, $params);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($c, CURLOPT_HTTPHEADER, [
				"Authorization: Bearer $token"
			]);

			$content = curl_exec($c);
			$http_status = curl_getinfo($c, CURLINFO_HTTP_CODE);

			curl_close($c);
			$responseData = json_decode($content->getBody(), true);

			if ($responseData['count'] !== 1) {
				throw new SmsTransmissionException();
			}
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return SMSApiConfig
	 */
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
