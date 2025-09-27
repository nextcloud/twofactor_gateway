<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Gateway;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Settings;
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

	public function isComplete(?Settings $settings = null): bool;

	public function createSettings(): Settings;

	public function getSettings(): Settings;

	public function cliConfigure(InputInterface $input, OutputInterface $output): int;

	public function remove(?Settings $settings = null): void;

	public static function getProviderId(): string;
}
