<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\Telegram;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;
use Psr\Log\LoggerInterface;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception as TelegramSDKException;

class Gateway implements IGateway {
	public function __construct(
		public GatewayConfig $gatewayConfig,
		public IAppConfig $config,
		private LoggerInterface $logger,
		private IL10N $l10n,
	) {
	}

	#[\Override]
	public function send(IUser $user, string $identifier, string $message, array $extra = []) {
		$message = $this->l10n->t('`%s` is your Nextcloud verification code.', [$extra['code']]);
		$this->logger->debug("sending telegram message to $identifier, message: $message");
		$botToken = $this->gatewayConfig->getBotToken();
		$this->logger->debug("telegram bot token: $botToken");

		$api = new BotApi($botToken);

		$this->logger->debug("sending telegram message to $identifier");
		try {
			$api->sendMessage($identifier, $message, parseMode: 'markdown');
		} catch (TelegramSDKException $e) {
			$this->logger->error($e);

			throw new MessageTransmissionException($e->getMessage());
		}
		$this->logger->debug("telegram message to chat $identifier sent");
	}
}
