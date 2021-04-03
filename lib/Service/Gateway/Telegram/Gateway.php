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
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception as TelegramSDKException;

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

		$api = new BotApi($botToken);

		$this->logger->debug("sending telegram message to $identifier");
		try {
			$api->sendMessage($identifier, $message);
		} catch (TelegramSDKException $e) {
			$this->logger->logException($e);

			throw new SmsTransmissionException($e);
		}
		$this->logger->debug("telegram message to chat $identifier sent");
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
