#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientCli;

use danog\MadelineProto\API;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** @psalm-suppress UndefinedClass */
#[AsCommand(name: 'telegram:complete-2fa', description: 'Complete Telegram 2FA for a pending login session')]
class Complete2faLogin extends AbstractMadelineCommand {
	#[\Override]
	protected function configure(): void {
		parent::configure();
		$this->addOption(
			'password',
			'p',
			InputOption::VALUE_REQUIRED,
			'Telegram 2FA password',
		);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$sessionDirectory = $this->resolveSessionDirectory($input, $output);
		if ($sessionDirectory === null) {
			return Command::FAILURE;
		}

		['logEnabled' => $logEnabled, 'logFile' => $logFile] = $this->resolveLogOptions($input, $sessionDirectory);
		$this->prepareSessionEnvironment($sessionDirectory, $logEnabled, $logFile);

		$password = (string)$input->getOption('password');
		if ($password === '') {
			$output->writeln('<error>Error: Telegram 2FA password is required.</error>');
			return Command::FAILURE;
		}

		try {
			$api = $this->buildMadelineApi($sessionDirectory, $logEnabled, $logFile);
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
