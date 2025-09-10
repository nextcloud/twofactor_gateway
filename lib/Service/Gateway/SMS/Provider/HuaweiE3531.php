<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Martin Keßler <martin@moegger.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class HuaweiE3531 implements IProvider {
	public const PROVIDER_ID = 'huawei_e3531';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private HuaweiE3531Config $config,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();
		$url = $config->getUrl();

		try {
			$sessionTokenResponse = $this->client->get("$url/webserver/SesTokInfo");
			$sessionTokenXml = simplexml_load_string($sessionTokenResponse->getBody());
			if ($sessionTokenXml === false) {
				throw new Exception();
			}

			$date = date('Y-m-d H:i:s');
			$messageEscaped = htmlspecialchars($message, ENT_XML1);

			$sendResponse = $this->client->post("$url/sms/send-sms", [
				'body' => "<request><Index>-1</Index><Phones><Phone>$identifier</Phone></Phones><Sca/><Content>$messageEscaped</Content><Length>-1</Length><Reserved>1</Reserved><Date>$date</Date></request>",
				'headers' => [
					'Cookie' => (string)$sessionTokenXml->SesInfo,
					'X-Requested-With' => 'XMLHttpRequest',
					'__RequestVerificationToken' => (string)$sessionTokenXml->TokInfo,
					'Content-Type' => 'text/xml',
				],
			]);
			$sendXml = simplexml_load_string($sendResponse->getBody());
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}

		if ((string)$sendXml !== 'OK') {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return HuaweiE3531Config
	 */
	#[\Override]
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
