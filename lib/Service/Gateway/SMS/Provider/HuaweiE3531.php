<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Martin Keßler <martin@moegger.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

/**
 * @method string getApi()
 * @method static setApi(string $api)
 */
class HuaweiE3531 extends AProvider {
	public const SCHEMA = [
		'id' => 'huawei_e3531',
		'name' => 'Huawei E3531',
		'fields' => [
			['field' => 'api', 'prompt' => 'Please enter the base URL of the Huawei E3531 stick: ', 'default' => 'http://192.168.8.1/api'],
		],
	];
	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$url = $this->getApi();

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
			throw new MessageTransmissionException();
		}

		if ((string)$sendXml !== 'OK') {
			throw new MessageTransmissionException();
		}
	}
}
