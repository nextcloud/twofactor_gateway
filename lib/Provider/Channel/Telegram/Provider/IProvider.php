<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\IAppConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface IProvider {
	public const SCHEMA = [];

	/**
	 * @param string $identifier
	 * @param string $message
	 *
	 * @throws MessageTransmissionException
	 */
	public function send(string $identifier, string $message);

	public static function idOverride(): ?string;

	public function getProviderId(): string;

	public function getSettings(): Settings;

	public function setAppConfig(IAppConfig $appConfig): void;

	public function cliConfigure(InputInterface $input, OutputInterface $output): int;
}
