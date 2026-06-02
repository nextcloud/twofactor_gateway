#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientCli;

use danog\MadelineProto\API;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'telegram:login', description: 'Login into Telegram using the Client API')]
class Login extends AbstractMadelineCommand {

	#[\Override]
	protected function configure(): void {
		parent::configure();
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$sessionDirectory = $this->resolveSessionDirectory($input, $output);
		if ($sessionDirectory === null) {
			return Command::FAILURE;
		}

		['logEnabled' => $logEnabled, 'logFile' => $logFile] = $this->resolveLogOptions($input, $sessionDirectory);
		$this->prepareSessionEnvironment($sessionDirectory, $logEnabled, $logFile);

		try {
			$api = $this->buildMadelineApi($sessionDirectory, $logEnabled, $logFile);
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
