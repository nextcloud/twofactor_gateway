<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\Telegram;

use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\Gateway\IGatewayConfig;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IUser;
use Psr\Log\LoggerInterface;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception as TelegramSDKException;

class Gateway implements IGateway {
	private IClient $client;

	public function __construct(
		IClientService $clientService,
		private GatewayConfig $gatewayConfig,
		private IAppConfig $config,
		private LoggerInterface $logger,
	) {
		$this->client = $clientService->newClient();
	}

	#[\Override]
	public function send(IUser $user, string $identifier, string $message) {
		$this->logger->debug("sending telegram message to $identifier, message: $message");
		$botToken = $this->gatewayConfig->getBotToken();
		$this->logger->debug("telegram bot token: $botToken");

		$api = new BotApi($botToken);

		$this->logger->debug("sending telegram message to $identifier");
		try {
			$api->sendMessage($identifier, $message);
		} catch (TelegramSDKException $e) {
			$this->logger->error($e);

			throw new SmsTransmissionException($e);
		}
		$this->logger->debug("telegram message to chat $identifier sent");
	}

	/**
	 * @return GatewayConfig
	 */
	#[\Override]
	public function getConfig(): IGatewayConfig {
		return $this->gatewayConfig;
	}
}
