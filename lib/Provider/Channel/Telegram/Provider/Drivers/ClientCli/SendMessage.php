#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientCli;

use danog\MadelineProto\API;
use danog\MadelineProto\RPCErrorException;
use danog\MadelineProto\Settings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'telegram:send-message', description: 'Send a message via Telegram Client API')]
class SendMessage extends AbstractMadelineCommand {

	#[\Override]
	protected function configure(): void {
		parent::configure();
		$this
			->addOption(
				'to',
				't',
				InputOption::VALUE_REQUIRED,
				'Recipient (username or user ID)',
			)
			->addOption(
				'message',
				'm',
				InputOption::VALUE_REQUIRED,
				'Message to send',
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

		try {
			$loggerSettings = $this->buildMadelineLoggerSettings($logEnabled, $logFile);

			$settings = (new Settings())
				->setLogger($loggerSettings);

			$message = $input->getOption('message');
			$peer = $input->getOption('to');

			$api = new API($sessionDirectory, $settings);
			/** @psalm-suppress UndefinedClass */
			if ($api->getAuthorization() !== API::LOGGED_IN) {
				$output->writeln('<error>Error: Telegram Client session is not logged in. Complete the Telegram login flow first.</error>');
				return Command::FAILURE;
			}

			$api->start();

			if (!str_starts_with($peer, '@')) {
				try {
					$result = $api->contacts->resolvePhone(phone: $peer);
					$peer = $result['peer'];
				} catch (RPCErrorException $e) {
					if ($e->getMessage() === 'PHONE_NOT_OCCUPIED') {
						$output->writeln('<error>Error: The phone number is not associated with any Telegram account.</error>');
						return Command::FAILURE;
					}
					throw $e;
				}

			}

			$api->messages->sendMessage([
				'peer' => $peer,
				'message' => $message,
				'parse_mode' => 'Markdown',
			]);

			$output->writeln('<info>Message sent successfully!</info>');

			return Command::SUCCESS;
		} catch (\Throwable $e) {
			$output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}
	}
}
