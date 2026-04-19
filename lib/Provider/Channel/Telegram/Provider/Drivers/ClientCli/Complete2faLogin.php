#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientCli;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** @psalm-suppress UndefinedClass */
#[AsCommand(name: 'telegram:complete-2fa', description: 'Complete Telegram 2FA for a pending login session')]
class Complete2faLogin extends Command {
	#[\Override]
	protected function configure(): void {
		$this
			->addOption(
				'session-directory',
				's',
				InputOption::VALUE_REQUIRED,
				'Directory to store the session files',
			)
			->addOption(
				'password',
				'p',
				InputOption::VALUE_REQUIRED,
				'Telegram 2FA password',
			);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$sessionDirectory = (string)$input->getOption('session-directory');
		$password = (string)$input->getOption('password');
		if ($password === '') {
			$output->writeln('<error>Error: Telegram 2FA password is required.</error>');
			return Command::FAILURE;
		}

		$appInfo = new AppInfo();
		$appInfo->setDeviceModel('Nextcloud-TwoFactor-Gateway');
		if ($apiId = getenv('TELEGRAM_API_ID')) {
			$appInfo->setApiId((int)$apiId);
		}
		if ($apiHash = getenv('TELEGRAM_API_HASH')) {
			$appInfo->setApiHash($apiHash);
		}

		try {
			$settings = (new Settings())
				->setAppInfo($appInfo)
				->setLogger((new Logger())->setExtra($sessionDirectory . '/MadelineProto.log'));

			$api = new API($sessionDirectory, $settings);
			if ($api->getAuthorization() === API::LOGGED_IN) {
				$output->writeln('{"status":"done"}');
				return Command::SUCCESS;
			}

			if ($api->getAuthorization() !== API::WAITING_PASSWORD) {
				$output->writeln('<error>Error: Telegram account is not currently waiting for a 2FA password.</error>');
				return Command::FAILURE;
			}

			$api->complete2faLogin($password);
			if ($api->getAuthorization() !== API::LOGGED_IN) {
				$output->writeln('<error>Error: Telegram 2FA password was accepted but login is still pending.</error>');
				return Command::FAILURE;
			}

			$output->writeln('{"status":"done"}');
			return Command::SUCCESS;
		} catch (\Throwable $e) {
			$output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}
	}
}
