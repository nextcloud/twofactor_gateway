<?php

declare(strict_types=1);

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

namespace OCA\TwoFactorGateway\Service\Gateway;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\Service\ISmsService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IUser;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class TelegramGateway implements ISmsService {

	/** @var IClient */
	private $client;

	/** @var IConfig */
	private $config;

	public function __construct(IClientService $clientService, IConfig $config) {
		$this->client = $clientService->newClient();
		$this->config = $config;
	}

	/**
	 * @param IUser $user
	 * @param string $recipient
	 * @param string $message
	 * @throws \Telegram\Bot\Exceptions\TelegramSDKException
	 */
	public function send(IUser $user, string $recipient, string $message) {
		$token = $this->config->getAppValue('twofactor_gateway', 'telegram_bot_token', null);
		// TODO: token missing handling

		$api = new Api($token);
		$chatId = $this->getChatId($user, $api);

		$api->sendMessage([
			'chat_id' => $chatId,
			'text' => $message,
		]);
	}

	private function getChatId(IUser $user, Api $api): int {
		$chatId = $this->config->getUserValue($user->getUID(), 'twofactor_gateway', 'telegram_chat_id', null);

		if (is_null($chatId)) {
			return (int)$chatId;
		}

		$userId = $this->config->getUserValue($user->getUID(), 'twofactor_gateway', 'telegram_user_id', null);
		$updates = $api->getUpdates();
		/** @var Update $update */
		$update = current(array_filter($updates, function (Update $data) use ($userId) {
			if ($data->message->text === "/start" && $data->message->from->id === $userId) {
				return true;
			}
			return false;
		}));
		// TODO: handle missing `/start` message and `$update` null values

		$chatId = $update->message->chat->id;
		$this->config->setUserValue($user->getUID(), 'twofactor_gateway', 'chat_id', $chatId);

		return (int)$chatId;
	}

}
