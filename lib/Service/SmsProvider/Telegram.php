<?php

/**
 * @authors Christoph Wurst <christoph@winzerhof-wurst.at> and Dretech <dretech@hetnetwerk.org>
 *
 * Nextcloud - Two-factor SMS for Telegram
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

class Telegram implements ISmsService {

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
		$telegram_url = $this->config->getAppValue('twofactor_sms', 'telegram_url');
		$telegram_bot_token = $this->config->getAppValue('twofactor_sms', 'telegram_bot_token');
		$telegram_user_id = "111111111"; # hard coded. I don't know where to store the Telegram User ID
		try {
       			$url = $telegram_url.$telegram_bot_token."/sendMessage?chat_id=".$telegram_user_id."&disable_web_page_preview=1&text=".$message;
			file_get_contents($url);

		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

}

