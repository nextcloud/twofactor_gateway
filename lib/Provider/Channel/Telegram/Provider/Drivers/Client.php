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
	private const CLI_COMMAND_TIMEOUT_SECONDS = 30;
	private const CLI_TIMEOUT_EXIT_CODE = 124;

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
				new FieldDefinition(
					field: 'madeline_log_enabled',
					prompt: 'Save MadelineProto diagnostics log file?',
					default: '0',
					optional: true,
					type: FieldType::BOOLEAN,
					helper: 'Disabled by default. Enable only when debugging Telegram Client issues.',
				),
				new FieldDefinition(
					field: 'madeline_log_path',
					prompt: 'MadelineProto log path (optional):',
					default: '',
					optional: true,
					type: FieldType::TEXT,
					helper: 'When empty, uses session directory inside Nextcloud data/appdata. Relative paths are resolved inside the session directory.',
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
			$errorMessage = $this->extractCliErrorMessage($output);
			return [
				'status' => 'error',
				'message' => $errorMessage !== ''
					? $errorMessage
					: 'Unable to generate Telegram login QR code for the current session.',
			];
		}

		return $this->extractCliJsonPayloadRaw($output);
	}

	public function resetLoginSession(): void {
		$this->exportApiCredentials();
		$output = [];
		$returnVar = 0;
		$this->executeCliCommand(
			$this->buildCliCommand('telegram:reset-login', [
				'session-directory' => $this->getSessionDirectory(),
			]),
			$output,
			$returnVar,
		);

		if ($returnVar === 0) {
			return;
		}

		// Fallback cleanup if the CLI reset command cannot attach to a stale IPC worker.
		$this->clearSessionDirectoryContents($this->getSessionDirectory());
	}

	/** @return array<string, string> */
	public function fetchLoggedInAccountInfo(): array {
		$this->exportApiCredentials();
		$lastOutput = [];
		$lastReturnVar = 0;

		for ($attempt = 1; $attempt <= 2; $attempt++) {
			$output = [];
			$returnVar = 0;
			$this->executeCliCommand(
				$this->buildCliCommand('telegram:get-account-info', [
					'session-directory' => $this->getSessionDirectory(),
				]),
				$output,
				$returnVar,
			);

			$lastOutput = $output;
			$lastReturnVar = $returnVar;

			if ($returnVar === 0) {
				$payload = $this->extractCliJsonPayload($output);
				if ($payload !== []) {
					return $payload;
				}
			}

			if ($attempt === 1) {
				usleep(200000);
			}
		}

		$this->logger->debug('Telegram account info unavailable after CLI query', [
			'returnVar' => $lastReturnVar,
			'output' => $lastOutput,
		]);

		return [];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function completeTwoFactorLogin(string $password): array {
		$this->exportApiCredentials();
		$output = [];
		$returnVar = 0;
		$this->executeCliCommand(
			$this->buildCliCommand('telegram:complete-2fa', [
				'session-directory' => $this->getSessionDirectory(),
				'password' => $password,
			]),
			$output,
			$returnVar,
		);

		if ($returnVar !== 0) {
			$errorMessage = $this->extractCliErrorMessage($output);
			return [
				'status' => 'error',
				'message' => $errorMessage !== ''
					? $errorMessage
					: 'Unable to complete Telegram 2FA login for the current session.',
			];
		}

		$payload = $this->extractCliJsonPayloadRaw($output);
		if ($payload !== []) {
			return $payload;
		}

		return ['status' => 'done'];
	}

	/**
	 * Starts the Telegram 2FA login completion in the background without blocking.
	 * Use interactiveSetupPollLogin to check for completion.
	 */
	public function startCompleteTwoFactorLoginBackground(string $password): void {
		$this->exportApiCredentials();
		$command = $this->buildCliCommand('telegram:complete-2fa', [
			'session-directory' => $this->getSessionDirectory(),
			'password' => $password,
		]);
		exec('nohup /bin/sh -c ' . escapeshellarg($command) . ' </dev/null >/dev/null 2>/dev/null &');
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

	protected function executeCliCommand(string $command, array &$output, ?int &$returnVar = null, int $timeoutSeconds = self::CLI_COMMAND_TIMEOUT_SECONDS): void {
		$descriptorSpec = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$process = proc_open(['/bin/sh', '-c', $command], $descriptorSpec, $pipes);
		if (!is_resource($process)) {
			exec($command, $output, $returnVar);
			return;
		}

		if (isset($pipes[0]) && is_resource($pipes[0])) {
			fclose($pipes[0]);
		}

		$stdout = '';
		$stderr = '';
		$timedOut = false;
		$deadline = microtime(true) + max(1, $timeoutSeconds);
		$lastStatus = null;

		if (isset($pipes[1]) && is_resource($pipes[1])) {
			stream_set_blocking($pipes[1], false);
		}
		if (isset($pipes[2]) && is_resource($pipes[2])) {
			stream_set_blocking($pipes[2], false);
		}

		while (true) {
			if (isset($pipes[1]) && is_resource($pipes[1])) {
				$chunk = stream_get_contents($pipes[1]);
				if (is_string($chunk) && $chunk !== '') {
					$stdout .= $chunk;
				}
			}

			if (isset($pipes[2]) && is_resource($pipes[2])) {
				$chunk = stream_get_contents($pipes[2]);
				if (is_string($chunk) && $chunk !== '') {
					$stderr .= $chunk;
				}
			}

			$status = proc_get_status($process);
			$lastStatus = $status;
			if (!is_array($status) || ($status['running'] ?? false) !== true) {
				break;
			}

			if (microtime(true) >= $deadline) {
				$timedOut = true;
				proc_terminate($process);
				usleep(100000);

				$afterTerminate = proc_get_status($process);
				if (is_array($afterTerminate) && ($afterTerminate['running'] ?? false) === true) {
					proc_terminate($process, 9);
				}

				$stderr .= "\nError: Telegram CLI command timed out after {$timeoutSeconds} seconds.";
				break;
			}

			usleep(50000);
		}

		if (isset($pipes[1]) && is_resource($pipes[1])) {
			$chunk = stream_get_contents($pipes[1]);
			if (is_string($chunk) && $chunk !== '') {
				$stdout .= $chunk;
			}
		}
		if (isset($pipes[2]) && is_resource($pipes[2])) {
			$chunk = stream_get_contents($pipes[2]);
			if (is_string($chunk) && $chunk !== '') {
				$stderr .= $chunk;
			}
		}

		if (isset($pipes[1]) && is_resource($pipes[1])) {
			fclose($pipes[1]);
		}
		if (isset($pipes[2]) && is_resource($pipes[2])) {
			fclose($pipes[2]);
		}

		$finalStatus = proc_get_status($process);
		$exitCode = proc_close($process);
		if ($timedOut) {
			$returnVar = self::CLI_TIMEOUT_EXIT_CODE;
		} else {
			$resolvedExitCode = null;

			if (is_array($finalStatus)) {
				$finalExitCode = $finalStatus['exitcode'] ?? null;
				if (is_int($finalExitCode) && $finalExitCode >= 0) {
					$resolvedExitCode = $finalExitCode;
				}
			}

			if ($resolvedExitCode === null && is_array($lastStatus)) {
				$lastExitCode = $lastStatus['exitcode'] ?? null;
				if (is_int($lastExitCode) && $lastExitCode >= 0) {
					$resolvedExitCode = $lastExitCode;
				}
			}

			if ($resolvedExitCode === null && is_int($exitCode) && $exitCode >= 0) {
				$resolvedExitCode = $exitCode;
			}

			$returnVar = $resolvedExitCode ?? 1;
		}

		$combined = '';
		if ($returnVar === 0 && is_string($stdout) && $stdout !== '') {
			// Successful command responses are expected in stdout (typically JSON payloads).
			$combined = $stdout;
		} else {
			if (is_string($stdout) && $stdout !== '') {
				$combined .= $stdout;
			}
			if (is_string($stderr) && $stderr !== '') {
				$combined .= ($combined !== '' ? "\n" : '') . $stderr;
			}
		}

		if ($combined === '') {
			$output = [];
			return;
		}

		$lines = preg_split('/\R/', rtrim($combined, "\r\n"));
		$output = is_array($lines) ? array_values(array_filter($lines, static fn ($line) => $line !== null && $line !== '')) : [];
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

		$options['log-enabled'] = $this->isMadelineLogEnabled() ? '1' : '0';
		$options['log-file'] = $this->resolveMadelineLogPath();

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

		if (stripos($errorMessage, 'timed out after') !== false) {
			return 'Failed to send Telegram message: request timed out. Verify that Telegram is connected on your mobile app and try again.';
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
	 */
	private function extractLastNonEmptyOutputLine(array $output): ?string {
		for ($index = count($output) - 1; $index >= 0; $index--) {
			$line = trim($output[$index]);
			if ($line !== '') {
				return $line;
			}
		}

		return null;
	}

	private function clearSessionDirectoryContents(string $directory): void {
		if (!is_dir($directory)) {
			return;
		}

		$entries = scandir($directory);
		if (!is_array($entries)) {
			return;
		}

		foreach ($entries as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = $directory . '/' . $entry;
			if (is_dir($path)) {
				$this->clearSessionDirectoryContents($path);
				@rmdir($path);
				continue;
			}

			@unlink($path);
		}
	}

	/**
	 * @param list<string> $output
	 * @return array<string, mixed>
	 */
	private function extractCliJsonPayloadRaw(array $output): array {
		$lastLine = $this->extractLastNonEmptyOutputLine($output);
		return is_string($lastLine) && $lastLine !== ''
			? $this->decodeCliJsonLine($lastLine)
			: [];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decodeCliJsonLine(string $line): array {
		$decoded = json_decode($line, true);
		return is_array($decoded) ? $this->sanitizeCliPayload($decoded) : [];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function sanitizeCliPayload(array $payload): array {
		$avatarUrl = $payload['account_avatar_url'] ?? null;
		if (!is_string($avatarUrl) || $avatarUrl === '' || !str_starts_with($avatarUrl, 'data:image/')) {
			return $payload;
		}

		if (!preg_match('/^data:([^;]+);base64,(.+)$/s', $avatarUrl, $matches)) {
			unset($payload['account_avatar_url']);
			return $payload;
		}

		$mimeType = strtolower(trim((string)($matches[1] ?? '')));
		$base64Data = preg_replace('/\s+/', '', (string)($matches[2] ?? ''));
		if (!is_string($base64Data) || $base64Data === '') {
			unset($payload['account_avatar_url']);
			return $payload;
		}

		$padding = strlen($base64Data) % 4;
		if ($padding !== 0) {
			$base64Data .= str_repeat('=', 4 - $padding);
		}

		$avatarBytes = base64_decode($base64Data, true);
		if (!is_string($avatarBytes) || $avatarBytes === '') {
			unset($payload['account_avatar_url']);
			return $payload;
		}

		if ($mimeType === 'image/jpeg' && !str_ends_with($avatarBytes, "\xFF\xD9")) {
			unset($payload['account_avatar_url']);
			return $payload;
		}

		if ($mimeType === 'image/png' && !str_ends_with($avatarBytes, "IEND\xAE\x42\x60\x82")) {
			unset($payload['account_avatar_url']);
			return $payload;
		}

		if (@getimagesizefromstring($avatarBytes) === false) {
			unset($payload['account_avatar_url']);
			return $payload;
		}

		$payload['account_avatar_url'] = sprintf('data:%s;base64,%s', $mimeType, base64_encode($avatarBytes));
		return $payload;
	}

	private function isMadelineLogEnabled(): bool {
		try {
			$raw = strtolower(trim($this->getMadelineLogEnabled()));
		} catch (\Throwable) {
			return false;
		}

		return in_array($raw, ['1', 'true', 'yes', 'on'], true);
	}

	private function resolveMadelineLogPath(): string {
		$defaultPath = $this->getSessionDirectory() . '/MadelineProto.log';

		try {
			$configured = trim($this->getMadelineLogPath());
		} catch (\Throwable) {
			return $defaultPath;
		}

		if ($configured === '') {
			return $defaultPath;
		}

		if (str_starts_with($configured, '/')) {
			return $configured;
		}

		return $this->getSessionDirectory() . '/' . ltrim($configured, '/');
	}

	/**
	 * @param list<string> $output
	 * @return array<string, string>
	 */
	private function extractCliJsonPayload(array $output): array {
		$lastLine = $this->extractLastNonEmptyOutputLine($output);
		if (!is_string($lastLine) || $lastLine === '') {
			return [];
		}

		$decoded = $this->decodeCliJsonLine($lastLine);
		if ($decoded === []) {
			return [];
		}

		$stringMap = [];
		foreach ($decoded as $key => $value) {
			if (is_string($key) && is_string($value)) {
				$stringMap[$key] = $value;
			}
		}

		return $stringMap;
	}
}
