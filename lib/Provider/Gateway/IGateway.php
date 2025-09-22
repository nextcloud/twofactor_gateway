<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Gateway;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\IUser;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface IGateway {
	/**
	 * @param IUser $user
	 * @param string $identifier
	 * @param string $message
	 * @param array $extra
	 *
	 * @throws MessageTransmissionException
	 */
	public function send(string $identifier, string $message, array $extra = []): void;

	public function isComplete(array $schema = []): bool;

	public function getSettings(): array;

	public function cliConfigure(InputInterface $input, OutputInterface $output): int;

	public function remove(array $schema = []): void;

	public static function getProviderId(): string;
}
