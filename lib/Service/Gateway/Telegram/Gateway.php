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

namespace OCA\TwoFactorGateway\Service\Gateway\Telegram;

use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\Gateway\IGatewayConfig;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUser;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class Gateway implements IGateway {

	/** @var IClient */
	private $client;

	/** @var GatewayConfig */
	private $gatewayConfig;

	/** @var IConfig */
	private $config;

	/** @var ILogger */
	private $logger;

	public function __construct(IClientService $clientService,
								GatewayConfig $gatewayConfig,
								IConfig $config,
								ILogger $logger) {
		$this->client = $clientService->newClient();
		$this->gatewayConfig = $gatewayConfig;
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * @param IUser $user
	 * @param string $identifier
	 * @param string $message
	 *
	 * @throws SmsTransmissionException
	 */
	public function send(IUser $user, string $identifier, string $message) {
		$this->logger->debug("sending telegram message to $identifier, message: $message");
		$botToken = $this->gatewayConfig->getBotToken();
		$this->logger->debug("telegram bot token: $botToken");

		$api = new Api($botToken);
		$chatId = $this->getChatId($user, $api, (int)$identifier);

		$this->logger->debug("sending telegram message to chat $chatId");
		$api->sendMessage([
			'chat_id' => $chatId,
			'text' => $message,
		]);
		$this->logger->debug("telegram message to chat $chatId sent");
	}

	private function getChatId(IUser $user, Api $api, int $userId): int {
		$chatId = $this->config->getUserValue($user->getUID(), 'twofactor_gateway', 'telegram_chat_id', null);

		if (!is_null($chatId)) {
			$this->logger->debug("using cached telegram chat id $chatId for user $userId");
			return (int)$chatId;
		}

		$this->logger->debug("trying to get chat id from updates");
		$updates = $api->getUpdates();
		$this->logger->debug("got " . count($updates) . " updates");
		/** @var Update $update */
		$update = current(array_filter($updates, function (Update $data) use ($userId) {
			if ($data->message->text === "/start" && $data->message->from->id === $userId) {
				$this->logger->debug("found `/start` message for user $userId");
				return true;
			}
			return false;
		}));
		// TODO: handle missing `/start` message and `$update` null values

		$chatId = $update->message->chat->id;
		$this->config->setUserValue($user->getUID(), 'twofactor_gateway', 'telegram_chat_id', $chatId);
		$this->logger->debug("found chat id $chatId for user $userId");

		return (int)$chatId;
	}

	/**
	 * Get the gateway-specific configuration
	 *
	 * @return IGatewayConfig
	 */
	public function getConfig(): IGatewayConfig {
		return $this->gatewayConfig;
	}

}
