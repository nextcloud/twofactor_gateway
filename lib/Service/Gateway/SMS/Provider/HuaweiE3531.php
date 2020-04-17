<?php

declare(strict_types=1);

/**
 * @author Martin Keßler <martin@moegger.de>
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

class HuaweiE3531 implements IProvider {
	public const PROVIDER_ID = 'huawei_e3531';

	/** @var IClient */
	private $client;

	/** @var HuaweiE3531Config */
	private $config;

	public function __construct(IClientService $clientService,
								HuaweiE3531Config $config) {
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
		$url = $config->getUrl();

		try {
			$sessionTokenResponse = $this->client->get("$url/webserver/SesTokInfo");
			$sessionTokenXml = simplexml_load_string($sessionTokenResponse->getBody());

			$date = date('Y-m-d H:i:s');
			$messageEscaped = htmlspecialchars($message, ENT_XML1);

			$sendResponse = $this->client->post("$url/sms/send-sms", [
				'body' => "<request><Index>-1</Index><Phones><Phone>$identifier</Phone></Phones><Sca/><Content>$messageEscaped</Content><Length>-1</Length><Reserved>1</Reserved><Date>$date</Date></request>",
				'headers' => [
					'Cookie' => $sessionTokenXml->SesInfo,
					'X-Requested-With' => 'XMLHttpRequest',
					'__RequestVerificationToken' => $sessionTokenXml->TokInfo,
					'Content-Type' => 'text/xml',
				],
			]);
			$sendXml = simplexml_load_string($sendResponse->getBody());
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}

		if ((string) $sendXml !== "OK") {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return HuaweiE3531Config
	 */
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
