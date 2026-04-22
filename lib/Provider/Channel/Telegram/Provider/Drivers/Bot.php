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
use OCA\TwoFactorGateway\Provider\FieldType;
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
	private const TELEGRAM_FILE_API_URL = 'https://api.telegram.org/file/bot';

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
					type: FieldType::SECRET,
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
			$telegramErrorDescription = $this->extractTelegramErrorDescription($e->getMessage());
			$safeMessage = $this->buildUserFacingErrorMessage($telegramErrorDescription);

			$this->logger->error('Failed to send Telegram message', [
				'exception' => $e,
				'chat_id' => $identifier,
			]);
			throw new MessageTransmissionException($safeMessage, 0, $e);
		}
	}

	private function buildUserFacingErrorMessage(?string $telegramErrorDescription): string {
		if ($telegramErrorDescription === null || trim($telegramErrorDescription) === '') {
			return 'Failed to send Telegram message.';
		}

		if (stripos($telegramErrorDescription, 'chat not found') !== false) {
			return 'Failed to send Telegram message: chat not found. Use your numeric Telegram ID and start a conversation with the bot first.';
		}

		return 'Failed to send Telegram message: ' . $telegramErrorDescription;
	}

	private function extractTelegramErrorDescription(string $errorMessage): ?string {
		if (preg_match('/"description"\s*:\s*"([^\"]+)"/', $errorMessage, $matches) === 1) {
			return stripcslashes($matches[1]);
		}

		if (preg_match('/Telegram API error:\s*(.+)$/', $errorMessage, $matches) === 1) {
			return trim($matches[1]);
		}

		return null;
	}

	/**
	 * @param array<string, string> $instanceConfig
	 * @return array<string, string>
	 */
	public function enrichTestResult(array $instanceConfig, string $identifier = ''): array {
		$chatId = trim($identifier);
		if ($chatId === '') {
			return [];
		}

		$token = trim((string)($instanceConfig['token'] ?? ''));
		if ($token === '') {
			$token = trim($this->getToken());
		}
		if ($token === '') {
			return [];
		}

		$chatData = $this->callTelegramApi($token, 'getChat', ['chat_id' => $chatId]);
		if ($chatData === null) {
			return [];
		}

		$accountName = $this->extractAccountName($chatData);
		if ($accountName === '') {
			return [];
		}

		$account = ['account_name' => $accountName];
		$avatarDataUri = $this->fetchAvatarDataUri($token, $chatData);
		if ($avatarDataUri !== '') {
			$account['account_avatar_url'] = $avatarDataUri;
		}

		return $account;
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>|null
	 */
	private function callTelegramApi(string $token, string $method, array $params): ?array {
		try {
			$client = $this->clientService->newClient();
			$response = $client->get(self::TELEGRAM_API_URL . $token . '/' . $method, [
				'query' => $params,
				'timeout' => 10,
			]);

			/** @var array<string, mixed>|null $data */
			$data = json_decode((string)$response->getBody(), true);
			if (!is_array($data) || ($data['ok'] ?? false) !== true || !isset($data['result']) || !is_array($data['result'])) {
				return null;
			}

			return $data['result'];
		} catch (\Throwable) {
			return null;
		}
	}

	/** @param array<string, mixed> $chatData */
	private function extractAccountName(array $chatData): string {
		$title = trim((string)($chatData['title'] ?? ''));
		if ($title !== '') {
			return $title;
		}

		$firstName = trim((string)($chatData['first_name'] ?? ''));
		$lastName = trim((string)($chatData['last_name'] ?? ''));
		$fullName = trim($firstName . ' ' . $lastName);
		if ($fullName !== '') {
			return $fullName;
		}

		$username = trim((string)($chatData['username'] ?? ''));
		if ($username !== '') {
			return '@' . ltrim($username, '@');
		}

		$id = trim((string)($chatData['id'] ?? ''));
		return $id;
	}

	/** @param array<string, mixed> $chatData */
	private function fetchAvatarDataUri(string $token, array $chatData): string {
		$photo = $chatData['photo'] ?? null;
		if (!is_array($photo)) {
			return '';
		}

		$fileId = trim((string)($photo['big_file_id'] ?? $photo['small_file_id'] ?? ''));
		if ($fileId === '') {
			return '';
		}

		$fileInfo = $this->callTelegramApi($token, 'getFile', ['file_id' => $fileId]);
		if ($fileInfo === null) {
			return '';
		}

		$filePath = trim((string)($fileInfo['file_path'] ?? ''));
		if ($filePath === '') {
			return '';
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->get(self::TELEGRAM_FILE_API_URL . $token . '/' . $filePath, ['timeout' => 10]);
			$avatarBody = (string)$response->getBody();
			if ($avatarBody === '') {
				return '';
			}

			return sprintf('data:%s;base64,%s', $this->detectAvatarMimeType($filePath), base64_encode($avatarBody));
		} catch (\Throwable) {
			return '';
		}
	}

	private function detectAvatarMimeType(string $filePath): string {
		$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
		return match ($extension) {
			'jpg', 'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp',
			default => 'image/jpeg',
		};
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
