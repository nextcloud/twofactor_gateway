<?php

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * Nextcloud - Two-factor SMS
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

namespace OCA\TwoFactorSms\Service\SmsProvider;

use Exception;
use OCA\TwoFactorSms\Exception\SmsTransmissionException;
use OCA\TwoFactorSms\Service\ISmsService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;

class WebSmsDe implements ISmsService {

	/** @var IClient */
	private $client;

	/** @var IConfig */
	private $config;

	/**
	 * @param IClientService $clientService
	 * @param IConfig $config
	 */
	public function __construct(IClientService $clientService, IConfig $config) {
		$this->client = $clientService->newClient();
		$this->config = $config;
	}

	/**
	 * @param string $recipient
	 * @param string $message
	 * @throws SmsTransmissionException
	 */
	public function send($recipient, $message) {
		$user = $this->config->getAppValue('twofactor_sms', 'websms_de_user');
		$password = $this->config->getAppValue('twofactor_sms', 'websms_de_password');
		try {
			$this->client->post('https://api.websms.com/rest/smsmessaging/text', [
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode("$user:$password"),
					'Content-Type' => 'application/json',
				],
				'json' => [
					'messageContent' => $message,
					'test' => false,
					'recipientAddressList' => [$recipient],
				],
			]);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

}
