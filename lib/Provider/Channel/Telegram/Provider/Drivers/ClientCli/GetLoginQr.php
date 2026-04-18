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
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$sessionDirectory = (string)$input->getOption('session-directory');

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
