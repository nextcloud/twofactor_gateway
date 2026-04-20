#!/usr/bin/env php
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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** @psalm-suppress UndefinedClass */
#[AsCommand(name: 'telegram:get-login-qr', description: 'Get Telegram login QR data for client authentication')]
class GetLoginQr extends Command {
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

	#[\Override]
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
			$authorization = $api->getAuthorization();
			if ($authorization === API::LOGGED_IN) {
				$output->writeln('{"status":"done"}');
				return Command::SUCCESS;
			}
			if ($authorization === API::WAITING_PASSWORD) {
				$payload = [
					'status' => 'needs_input',
					'step' => 'enter_password',
					'hint' => $api->getHint(),
				];
				$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
				if (!is_string($json)) {
					$output->writeln('<error>Error: Unable to serialize Telegram 2FA login state.</error>');
					return Command::FAILURE;
				}

				$output->writeln($json);
				return Command::SUCCESS;
			}

			$qrLogin = $api->qrLogin();
			if ($qrLogin === null) {
				$output->writeln('<error>Error: Unable to generate Telegram login QR code for the current session.</error>');
				return Command::FAILURE;
			}

			$payload = [
				'status' => 'pending',
				'link' => $qrLogin->link,
				'qr_svg' => $qrLogin->getQRSvg(280, 1),
				'expires_in' => $qrLogin->expiresIn(),
			];

			$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			if (!is_string($json)) {
				$output->writeln('<error>Error: Unable to serialize Telegram login QR data.</error>');
				return Command::FAILURE;
			}

			$output->writeln($json);
			return Command::SUCCESS;
		} catch (\Throwable $e) {
			$output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}
	}
}
