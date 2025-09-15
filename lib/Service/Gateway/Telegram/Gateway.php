<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\Telegram;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\Gateway\IGatewayConfig;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception as TelegramSDKException;

class Gateway implements IGateway {
	public function __construct(
		private GatewayConfig $gatewayConfig,
		public IAppConfig $config,
		private LoggerInterface $logger,
		private IL10N $l10n,
	) {
	}

	#[\Override]
	public function send(IUser $user, string $identifier, string $message, array $extra = []): void {
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

	/**
	 * @return GatewayConfig
	 */
	#[\Override]
	public function getConfig(): IGatewayConfig {
		return $this->gatewayConfig;
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$helper = new QuestionHelper();
		$tokenQuestion = new Question($this->gatewayConfig::SCHEMA['fields'][0]['prompt']);
		$token = $helper->ask($input, $output, $tokenQuestion);
		$this->gatewayConfig->setBotToken($token);
		$output->writeln("Using $token.");

		$this->gatewayConfig->setBotToken($token);
		return 0;
	}
}
