<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\AProvider;
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
class Client extends AProvider {
	public const SCHEMA = [
		'name' => 'Telegram Client API',
		'instructions' => <<<HTML
			<p>Enter your Telegram number or username to receive your verification code below.</p>
			HTML,
		'fields' => [
			['field' => 'bot_token', 'prompt' => 'Please enter your Telegram bot token:'],
		],
	];
	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
	) {
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
		// if (PHP_VERSION_ID < 80200) {
		// 	$output->writeln('The Telegram Client API provider requires PHP 8.2 or higher.');

		// 	return 1;
		// }

		require __DIR__ . '/../../../../../../vendor-bin/telegram-client/vendor/autoload.php';
		// $helper = new QuestionHelper();
		// $tokenQuestion = new Question(self::SCHEMA['fields'][0]['prompt'] . ' ');
		// $token = $helper->ask($input, $output, $tokenQuestion);
		// $this->setBotToken($token);
		// $output->writeln("Using $token.");

		// $this->setBotToken($token);
		return 0;
	}
}
