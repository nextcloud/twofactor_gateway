<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\Telegram;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Service\Gateway\AGateway;
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

/**
 * @method string getBotToken()
 * @method static setBotToken(string $botToken)
 */
class Gateway extends AGateway {
	public const SCHEMA = [
		'id' => 'telegram',
		'name' => 'Telegram',
		'fields' => [
			['field' => 'bot_token', 'prompt' => 'Please enter your Telegram bot token:'],
		],
	];
	public function __construct(
		public IAppConfig $appConfig,
		private LoggerInterface $logger,
		private IL10N $l10n,
	) {
		parent::__construct($appConfig);
	}

	#[\Override]
	public function send(IUser $user, string $identifier, string $message, array $extra = []): void {
		$message = $this->l10n->t('`%s` is your Nextcloud verification code.', [$extra['code']]);
		$this->logger->debug("sending telegram message to $identifier, message: $message");
		$botToken = $this->getBotToken();
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

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$helper = new QuestionHelper();
		$tokenQuestion = new Question(self::SCHEMA['fields'][0]['prompt']);
		$token = $helper->ask($input, $output, $tokenQuestion);
		$this->setBotToken($token);
		$output->writeln("Using $token.");

		$this->setBotToken($token);
		return 0;
	}
}
