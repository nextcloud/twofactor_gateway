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
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * @method string getToken()
 * @method static setToken(string $token)
 */
class Bot extends AProvider {
	private const TELEGRAM_API_URL = 'https://api.telegram.org/bot';

	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IClientService $clientService,
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
					field: 'token',
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
		$token = $this->getToken();
		$this->logger->debug("telegram bot token: $token");

		$url = self::TELEGRAM_API_URL . $token . '/sendMessage';
		$params = [
			'chat_id' => $identifier,
			'text' => $message,
			'parse_mode' => 'markdown',
		];

		$this->logger->debug("sending telegram message to $identifier");
		try {
			$client = $this->clientService->newClient();
			$response = $client->post($url, [
				'json' => $params,
				'timeout' => 10,
			]);

			$body = $response->getBody();
			$data = json_decode($body, true);

			if (!isset($data['ok']) || $data['ok'] !== true) {
				$errorDescription = $data['description'] ?? 'Unknown error';
				$this->logger->error('Telegram API error: ' . $errorDescription);
				throw new MessageTransmissionException('Telegram API error: ' . $errorDescription);
			}

			$this->logger->debug("telegram message to chat $identifier sent");
		} catch (RuntimeException $e) {
			$this->logger->error('Failed to send Telegram message', [
				'exception' => $e,
				'chat_id' => $identifier,
			]);
			throw new MessageTransmissionException('Failed to send Telegram message: ' . $e->getMessage(), 0, $e);
		}
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$helper = new QuestionHelper();
		$settings = $this->getSettings();
		$tokenQuestion = new Question($settings->fields[0]->prompt . ' ');
		$token = $helper->ask($input, $output, $tokenQuestion);
		$this->setToken($token);
		$output->writeln("Using $token.");
		return 0;
	}
}
