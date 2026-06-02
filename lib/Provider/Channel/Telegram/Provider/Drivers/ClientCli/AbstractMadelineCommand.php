<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientCli;

use danog\MadelineProto\API;
use danog\MadelineProto\Logger as MadelineLogger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** @psalm-suppress UndefinedClass */
abstract class AbstractMadelineCommand extends Command {
	#[\Override]
	protected function configure(): void {
		$this->addOption(
			'session-directory',
			's',
			InputOption::VALUE_REQUIRED,
			'Directory to store the session files',
		);
		$this->addOption(
			'log-enabled',
			null,
			InputOption::VALUE_REQUIRED,
			'Whether to persist MadelineProto diagnostics log to file (1/0, true/false)',
			'0',
		);
		$this->addOption(
			'log-file',
			null,
			InputOption::VALUE_REQUIRED,
			'Absolute path to MadelineProto diagnostics log file',
			'',
		);
	}

	protected function resolveSessionDirectory(InputInterface $input, OutputInterface $output): ?string {
		$sessionDirectory = rtrim((string)$input->getOption('session-directory'), '/\\');
		if ($sessionDirectory === '') {
			$output->writeln('<error>Error: Session directory is required.</error>');
			return null;
		}
		return $sessionDirectory;
	}

	/** @return array{logEnabled: bool, logFile: string} */
	protected function resolveLogOptions(InputInterface $input, string $sessionDirectory): array {
		$logEnabledRaw = strtolower(trim((string)$input->getOption('log-enabled')));
		$logEnabled = in_array($logEnabledRaw, ['1', 'true', 'yes', 'on'], true);
		$configuredLogFile = trim((string)$input->getOption('log-file'));
		$logFile = $logEnabled
			? ($configuredLogFile !== '' ? $configuredLogFile : $sessionDirectory . '/MadelineProto.log')
			: '';
		return ['logEnabled' => $logEnabled, 'logFile' => $logFile];
	}

	protected function prepareSessionEnvironment(string $sessionDirectory, bool $logEnabled, string $logFile): void {
		if (!is_dir($sessionDirectory)) {
			@mkdir($sessionDirectory, 0700, true);
		}
		if (is_dir($sessionDirectory)) {
			@chdir($sessionDirectory);
		}
		if ($logEnabled) {
			$logDir = dirname($logFile);
			if ($logDir !== '' && !is_dir($logDir)) {
				@mkdir($logDir, 0700, true);
			}
		}
		@ini_set('error_log', '/dev/null');
	}

	protected function buildMadelineLoggerSettings(bool $logEnabled, string $logFile): Logger {
		$loggerSettings = (new Logger())
			->setLevel(MadelineLogger::LEVEL_NOTICE);
		if ($logEnabled) {
			$loggerSettings
				->setType(MadelineLogger::LOGGER_FILE)
				->setExtra($logFile);
		} else {
			$loggerSettings
				->setType(MadelineLogger::LOGGER_CALLABLE)
				->setExtra(static function (): void {
				});
		}
		return $loggerSettings;
	}

	protected function buildMadelineApi(string $sessionDirectory, bool $logEnabled, string $logFile): API {
		$appInfo = new AppInfo();
		$appInfo->setDeviceModel('Nextcloud-TwoFactor-Gateway');
		if ($apiId = getenv('TELEGRAM_API_ID')) {
			$appInfo->setApiId((int)$apiId);
		}
		if ($apiHash = getenv('TELEGRAM_API_HASH')) {
			$appInfo->setApiHash($apiHash);
		}

		$settings = (new Settings())
			->setAppInfo($appInfo)
			->setLogger($this->buildMadelineLoggerSettings($logEnabled, $logFile));

		return new API($sessionDirectory, $settings);
	}
}
