#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use danog\MadelineProto\API;
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
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$sessionDirectory = $input->getOption('session-directory');

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

			if (empty(getenv('TELEGRAM_API_ID'))) {
				$api->start();
				$output->writeln(<<<MESSAGE

					<info>Telegram login successful! Run again the twofactor setup command to finish configuration.</info>

					MESSAGE);
			}


			return Command::SUCCESS;
		} catch (\Throwable $e) {
			$output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}
	}
}
