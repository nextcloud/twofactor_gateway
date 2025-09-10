<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Marcin Kot <kodek11@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class SMSApi implements IProvider {
	public const PROVIDER_ID = 'smsapi.com';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private SMSApiConfig $config,
	) {
		$this->client = $clientService->newClient();
		$this->config = $config;
	}

	#[\Override]
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
			if ($content === false) {
				throw new SmsTransmissionException();
			}
			$http_status = curl_getinfo($c, CURLINFO_HTTP_CODE);

			curl_close($c);
			$responseData = json_decode($content, true);

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
	#[\Override]
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
