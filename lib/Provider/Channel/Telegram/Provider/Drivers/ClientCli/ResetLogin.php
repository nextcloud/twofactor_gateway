#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientCli;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** @psalm-suppress UndefinedClass */
#[AsCommand(name: 'telegram:reset-login', description: 'Reset Telegram login state for current session directory')]
class ResetLogin extends AbstractMadelineCommand {
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
			try {
				$api->logout();
			} catch (\Throwable) {
				// Best effort cleanup; continue with success payload.
			}

			$output->writeln('{"status":"done"}');
			return Command::SUCCESS;
		} catch (\Throwable $e) {
			$output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}
	}
}
