#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use danog\MadelineProto\API;
use danog\MadelineProto\RPCErrorException;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'telegram:send-message', description: 'Send a message via Telegram Client API')]
class SendMessage extends Command {

	protected function configure(): void {
		$this
			->addOption(
				'session-directory',
				's',
				InputOption::VALUE_REQUIRED,
				'Directory to store the session files',
			)
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

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$sessionDirectory = $input->getOption('session-directory');

		try {
			$settings = (new Settings())
				->setLogger((new Logger())->setExtra($sessionDirectory . '/MadelineProto.log'));

			$message = $input->getOption('message');
			$peer = $input->getOption('to');

			$api = new API($sessionDirectory, $settings);

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
