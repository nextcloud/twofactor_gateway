<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\AProvider;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Vendor\TelegramBot\Api\BotApi;
use OCA\TwoFactorGateway\Vendor\TelegramBot\Api\Exception as TelegramSDKException;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * @method string getBotToken()
 * @method static setBotToken(string $botToken)
 */
class Bot extends AProvider {
	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
	) {
	}

	public function createSettings() {
		return new Settings(
			id: 'telegram_bot',
			name: 'Telegram Bot',
			allowMarkdown: true,
			instructions: <<<HTML
				<p>In order to receive authentication codes via Telegram, you first
				have to start a new chat with the bot set up by your admin.</p>
				<p>Secondly, you have to obtain your Telegram ID via the
				<a href="https://telegram.me/getmyid_bot" target="_blank" rel="noreferrer noopener">ID Bot</a>.</p>
				<p>Enter this ID to receive your verification code below.</p>
				HTML,
			fields: [
				new FieldDefinition(
					field: 'bot_token',
					prompt: 'Please enter your Telegram bot token:',
				),
			]
		);
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		if (empty($message)) {
			$message = $this->l10n->t('`%s` is your Nextcloud verification code.', [$extra['code']]);
		}
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
		$settings = $this->getSettings();
		$tokenQuestion = new Question($settings->fields[0]->prompt . ' ');
		$token = $helper->ask($input, $output, $tokenQuestion);
		$this->setBotToken($token);
		$output->writeln("Using $token.");
		return 0;
	}
}
