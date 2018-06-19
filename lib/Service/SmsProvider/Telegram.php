<?php

declare(strict_types = 1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author André Fondse <andre@hetnetwerk.org>
 *
 * Nextcloud - Two-factor Gateway for Telegram
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

namespace OCA\TwoFactorGateway\Service\SmsProvider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\Service\ISmsService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;

class Telegram implements ISmsService {

	/** @var IClient */
	private $client;

	/** @var IConfig */
	private $config;

	public function __construct(IClientService $clientService, IConfig $config) {
		$this->client = $clientService->newClient();
		$this->config = $config;
	}

	/**
	 * @throws SmsTransmissionException
	 */
	public function send(string $recipient, string $message) {
		$telegramUrl = $this->config->getAppValue('twofactor_gateway', 'telegram_url');
		$telegramBotToken = $this->config->getAppValue('twofactor_gateway', 'telegram_bot_token');
		$telegramUserId = $this->config->getUserValue('nextclouddev', 'twofactor_gateway', 'telegram_id');
		try {
			$url = $telegramUrl . $telegramBotToken . "/sendMessage?chat_id=$telegramUserId&disable_web_page_preview=1&text=" . urlencode($message);
			$this->client->get($url);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

}
