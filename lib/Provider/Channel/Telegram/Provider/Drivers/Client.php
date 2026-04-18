<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Command\FieldQuestionPrompter;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\AProvider;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\FieldType;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method string getBotToken()
 * @method static setBotToken(string $botToken)
 * @method string getApiId()
 * @method static setApiId(string $apiId)
 * @method string getApiHash()
 * @method static setApiHash(string $apiHash)
 */
class Client extends AProvider {
	private const LOGIN_REQUIRED_MESSAGE = 'Telegram Client session is not logged in. Complete the Telegram login flow for this gateway before testing or sending messages.';

	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IAppData $appData,
		private IConfig $config,
	) {
	}

	public function createSettings() {
		return new Settings(
			id: 'telegram_client',
			name: 'Telegram Client API',
			allowMarkdown: true,
			instructions: <<<HTML
				<p>Enter your full phone number including country code (e.g. +491751234567) as identifier or your Telegram user name preceded by an `@` (e.g. `@myusername`).</p>
				HTML,
			fields: [
				new FieldDefinition(
					field: 'api_id',
					prompt: 'Please enter your Telegram api_id:',
					helper: 'Get one at https://my.telegram.org/apps',
				),
				new FieldDefinition(
					field: 'api_hash',
					prompt: 'Please enter your Telegram api_hash:',
					helper: 'Get one at https://my.telegram.org/apps',
					type: FieldType::SECRET,
				),
			]
		);
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		$this->logger->debug("sending telegram message to $identifier, message: $message");

		$this->exportApiCredentials();
		$output = [];
		$returnVar = 0;
		$this->executeCliCommand(
			$this->buildCliCommand('telegram:send-message', [
				'session-directory' => $this->getSessionDirectory(),
				'to' => $identifier,
				'message' => $message,
			]),
			$output,
			$returnVar,
		);

		if ($returnVar !== 0) {
			$this->logger->error('Error sending Telegram message', ['output' => $output, 'returnVar' => $returnVar]);
			throw new MessageTransmissionException($this->buildUserFacingCliErrorMessage($output));
		}

		$this->logger->debug("telegram message to chat $identifier sent");
	}

	/**
	 * @param array<string, string> $instanceConfig
	 * @return array<string, string>
	 */
	public function enrichTestResult(array $instanceConfig, string $identifier = ''): array {
		return $this->fetchLoggedInAccountInfo();
	}

	/** @return array<string, mixed> */
	public function fetchLoginQrCode(): array {
		$this->exportApiCredentials();
		$output = [];
		$returnVar = 0;
		$this->executeCliCommand(
			$this->buildCliCommand('telegram:get-login-qr', [
				'session-directory' => $this->getSessionDirectory(),
			]),
			$output,
			$returnVar,
		);

		if ($returnVar !== 0) {
			return [];
		}

		return $this->extractCliJsonPayloadRaw($output);
	}

	/** @return array<string, string> */
	public function fetchLoggedInAccountInfo(): array {
		$this->exportApiCredentials();
		$output = [];
		$returnVar = 0;
		$this->executeCliCommand(
			$this->buildCliCommand('telegram:get-account-info', [
				'session-directory' => $this->getSessionDirectory(),
			]),
			$output,
			$returnVar,
		);

		if ($returnVar !== 0) {
			return [];
		}

		return $this->extractCliJsonPayload($output);
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$settings = $this->getSettings();
		$helper = new QuestionHelper();
		$fieldPrompter = new FieldQuestionPrompter();
		$apiId = $fieldPrompter->askValue($settings->fields[0], $input, $output, $helper);
		$apiHash = $fieldPrompter->askValue($settings->fields[1], $input, $output, $helper);

		$this->setApiId($apiId);
		$this->setApiHash($apiHash);

		$this->exportApiCredentials();

		$cmd = $this->buildCliCommand('telegram:login', [
			'session-directory' => $this->getSessionDirectory(),
		]);

		$user = posix_getpwuid(posix_getuid());
		$userName = is_array($user) ? (string)($user['name'] ?? '') : '';

		$output->writeln('<info>Starting the Telegram login flow...</info>');
		$output->writeln('');
		if ($userName !== '') {
			$output->writeln('Make sure that the user running this command matches the web server user: <info>' . $userName . '</info>.');
			$output->writeln('');
		}
		$output->writeln('If a QR code is shown, scan it in Telegram via Settings > Devices > Link Desktop Device.');
		$output->writeln('');
		$exitCode = 0;
		$this->executeInteractiveCliCommand($cmd, $exitCode);
		if ($exitCode !== 0) {
			return (int)$exitCode;
		}

		$output->writeln('');
		$output->writeln('<info>Telegram login completed.</info>');
		return 0;
	}

	protected function executeCliCommand(string $command, array &$output, ?int &$returnVar = null): void {
		exec($command, $output, $returnVar);
	}

	protected function executeInteractiveCliCommand(string $command, ?int &$returnVar = null): void {
		passthru($command, $returnVar);
	}

	protected function getSessionDirectory(): string {

		try {
			$folder = $this->appData->newFolder('session.madeline');
		} catch (NotFoundException) {
			$folder = $this->appData->getFolder('session.madeline');
		}

		$instanceId = $this->config->getSystemValueString('instanceid');
		$appDataFolder = 'appdata_' . $instanceId;
		$dataDirectory = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data');
		$fullPath = $dataDirectory . '/' . $appDataFolder . '/' . Application::APP_ID . '/session.madeline';

		if (is_dir($fullPath) === false) {
			$reflection = new \ReflectionClass($folder);
			$reflectionProperty = $reflection->getProperty('folder');
			$reflectionProperty->setAccessible(true);
			$folder = $reflectionProperty->getValue($folder);
			$fullPath = $folder->getInternalPath();
		}
		return $fullPath;
	}

	private function exportApiCredentials(): void {
		putenv('TELEGRAM_API_ID=' . $this->getApiId());
		putenv('TELEGRAM_API_HASH=' . $this->getApiHash());
	}

	/**
	 * @param array<string, string> $options
	 */
	private function buildCliCommand(string $command, array $options): string {
		$path = realpath(__DIR__ . '/ClientCli/Cli.php');
		if ($path === false) {
			throw new \RuntimeException('Telegram Client CLI entrypoint not found.');
		}

		$cmd = 'php ' . escapeshellarg($path) . ' ' . $command;
		foreach ($options as $name => $value) {
			$cmd .= ' --' . $name . ' ' . escapeshellarg($value);
		}

		return $cmd;
	}

	/**
	 * @param list<string> $output
	 */
	private function buildUserFacingCliErrorMessage(array $output): string {
		if ($this->looksLikeLoginRequiredOutput($output)) {
			return self::LOGIN_REQUIRED_MESSAGE;
		}

		$errorMessage = $this->extractCliErrorMessage($output);
		if ($errorMessage === '') {
			return 'Failed to send Telegram message.';
		}

		if (stripos($errorMessage, 'PHONE_NOT_OCCUPIED') !== false) {
			return 'Failed to send Telegram message: the phone number is not associated with any Telegram account.';
		}

		return 'Failed to send Telegram message: ' . $errorMessage;
	}

	/**
	 * @param list<string> $output
	 */
	private function looksLikeLoginRequiredOutput(array $output): bool {
		foreach ($output as $line) {
			$normalized = strtolower($this->normalizeCliOutputLine($line));
			if (str_contains($normalized, 'not logged in')) {
				return true;
			}
			if (str_contains($normalized, 'scan the above qr code') || str_contains($normalized, 'login manually')) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param list<string> $output
	 */
	private function extractCliErrorMessage(array $output): string {
		for ($index = count($output) - 1; $index >= 0; $index--) {
			$line = $this->normalizeCliOutputLine($output[$index]);
			if ($line === '') {
				continue;
			}

			if (str_starts_with($line, 'Error:')) {
				return trim(substr($line, strlen('Error:')));
			}

			if (stripos($line, 'fatal error') !== false || stripos($line, 'exception') !== false) {
				return $line;
			}
		}

		return '';
	}

	private function normalizeCliOutputLine(string $line): string {
		$line = preg_replace('/\e\[[\d;]*m/', '', $line) ?? $line;
		$line = strip_tags($line);
		return trim($line);
	}

	/**
	 * @param list<string> $output
	 * @return array<string, mixed>
	 */
	private function extractCliJsonPayloadRaw(array $output): array {
		for ($index = count($output) - 1; $index >= 0; $index--) {
			$line = trim($output[$index]);
			if ($line === '') {
				continue;
			}

			$decoded = json_decode($line, true);
			if (is_array($decoded)) {
				return $decoded;
			}
		}

		return [];
	}

	/**
	 * @param list<string> $output
	 * @return array<string, string>
	 */
	private function extractCliJsonPayload(array $output): array {
		$decoded = $this->extractCliJsonPayloadRaw($output);
		if ($decoded !== []) {
			$result = [];
			foreach ($decoded as $key => $value) {
				if (is_string($key) && is_string($value)) {
					$result[$key] = $value;
				}
			}

			return $result;
		}

		return [];
	}
}
