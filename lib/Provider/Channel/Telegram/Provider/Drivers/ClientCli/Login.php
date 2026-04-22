#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientCli;

use danog\MadelineProto\API;
use danog\MadelineProto\Logger as MadelineLogger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'telegram:login', description: 'Login into Telegram using the Client API')]
class Login extends Command {

	protected function configure(): void {
		$this
			->addOption(
				'session-directory',
				's',
				InputOption::VALUE_REQUIRED,
				'Directory to store the session files',
			)
			->addOption(
				'log-enabled',
				null,
				InputOption::VALUE_REQUIRED,
				'Whether to persist MadelineProto diagnostics log to file (1/0, true/false)',
				'0',
			)
			->addOption(
				'log-file',
				null,
				InputOption::VALUE_REQUIRED,
				'Absolute path to MadelineProto diagnostics log file',
				'',
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$sessionDirectory = rtrim((string)$input->getOption('session-directory'), '/\\');
		if ($sessionDirectory === '') {
			$output->writeln('<error>Error: Session directory is required.</error>');
			return Command::FAILURE;
		}

		$logEnabledRaw = strtolower(trim((string)$input->getOption('log-enabled')));
		$logEnabled = in_array($logEnabledRaw, ['1', 'true', 'yes', 'on'], true);
		$configuredLogFile = trim((string)$input->getOption('log-file'));
		$logFile = $logEnabled
			? ($configuredLogFile !== '' ? $configuredLogFile : $sessionDirectory . '/MadelineProto.log')
			: '';
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

		$appInfo = new AppInfo();
		$appInfo->setDeviceModel('Nextcloud-TwoFactor-Gateway');
		if ($apiId = getenv('TELEGRAM_API_ID')) {
			$appInfo->setApiId((int)$apiId);
		}
		if ($apiHash = getenv('TELEGRAM_API_HASH')) {
			$appInfo->setApiHash($apiHash);
		}

		try {
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

			$settings = (new Settings())
				->setAppInfo($appInfo)
				->setLogger($loggerSettings);

			$api = new API($sessionDirectory, $settings);
			/** @psalm-suppress UndefinedClass */
			if ($api->getAuthorization() === API::LOGGED_IN) {
				$output->writeln('<info>Telegram login already completed for this session.</info>');
				return Command::SUCCESS;
			}

			$api->start();
			$output->writeln(<<<MESSAGE

				<info>Telegram login successful! Run again the twofactor setup command to finish configuration.</info>

				MESSAGE);


			return Command::SUCCESS;
		} catch (\Throwable $e) {
			$output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}
	}
}
