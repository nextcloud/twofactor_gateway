<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\AProvider;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * @method string getBotToken()
 * @method static setBotToken(string $botToken)
 * @method string getApiId()
 * @method static setApiId(string $apiId)
 * @method string getApiHash()
 * @method static setApiHash(string $apiHash)
 */
class Client extends AProvider {
	public const SCHEMA = [
		'id' => 'telegram_client',
		'name' => 'Telegram Client API',
		'allow_markdown' => true,
		'instructions' => <<<HTML
			<p>Enter your full phone number including country code (e.g. +491751234567) as identifier or your Telegram user name preceded by an `@` (e.g. `@myusername`).</p>
			HTML,
		'fields' => [
			['field' => 'api_id', 'prompt' => 'Please enter your Telegram api_id:'],
			['field' => 'api_hash', 'prompt' => 'Please enter your Telegram api_hash:'],
		],
	];
	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IAppData $appData,
		private IConfig $config,
	) {
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		$this->logger->debug("sending telegram message to $identifier, message: $message");

		$sessionFile = $this->getSessionDirectory();

		putenv('TELEGRAM_API_ID=' . $this->getApiId());
		putenv('TELEGRAM_API_HASH=' . $this->getApiHash());

		$path = realpath(__DIR__ . '/ClientCli/Cli.php');
		$cmd = 'php ' . escapeshellarg($path) . ' '
			. 'telegram:send-message '
			. '--session-directory ' . escapeshellarg($sessionFile)
			. ' --to ' . escapeshellarg($identifier)
			. ' --message ' . escapeshellarg($message);

		exec($cmd, $output, $returnVar);

		if ($returnVar !== 0) {
			$this->logger->error('Error sending Telegram message', ['output' => $output, 'returnVar' => $returnVar]);
			throw new MessageTransmissionException();
		}

		$this->logger->debug("telegram message to chat $identifier sent");
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		if (PHP_VERSION_ID < 80200) {
			$output->writeln('The Telegram Client API provider requires PHP 8.2 or higher.');
			return 1;
		}

		$telegramApiQuestion = new Question('Please enter your api_id (get one at https://my.telegram.org/apps): ');
		$helper = new QuestionHelper();
		$apiId = $helper->ask($input, $output, $telegramApiQuestion);
		$apiHashQuestion = new Question('Please enter your api_hash (get one at https://my.telegram.org/apps): ');
		$apiHash = $helper->ask($input, $output, $apiHashQuestion);

		$this->setApiId($apiId);
		$this->setApiHash($apiHash);

		putenv('TELEGRAM_API_ID=' . $apiId);
		putenv('TELEGRAM_API_HASH=' . $apiHash);

		$sessionFile = $this->getSessionDirectory();

		$path = realpath(__DIR__ . '/ClientCli/Cli.php');
		$cmd = 'php ' . escapeshellarg($path) . ' telegram:login --session-directory ' . escapeshellarg($sessionFile);

		// This is only to create the client session files.
		// The login will be made afterwards.
		exec($cmd);

		$user = posix_getpwuid(posix_getuid());

		$output->writeln('<info>Run the following command to start the Telegram login process:</info>');
		$output->writeln('');
		$output->writeln("<comment>$cmd</comment>");
		$output->writeln('');
		$output->writeln('Make sure that the user to run the command is the same as the web server user: <info>' . $user['name'] . '</info>.');
		$output->writeln('');
		$output->writeln('Follow the instructions in the command output.');
		return 0;
	}

	private function getSessionDirectory(): string {

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
}
