<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\AbstractChannelAProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AProvider extends AbstractChannelAProvider implements IProvider {

	#[\Override]
	protected static function getDriverNamespacePrefix(): string {
		return 'OCA\\TwoFactorGateway\\Provider\\Channel\\Telegram\\Provider\\Drivers\\';
	}

	/**
	 * @throws MessageTransmissionException
	 */
	#[\Override]
	abstract public function send(string $identifier, string $message);

	#[\Override]
	abstract public function cliConfigure(InputInterface $input, OutputInterface $output): int;

	/**
	 * Clone provider with an ephemeral runtime configuration.
	 *
	 * @param array<string, string> $config
	 */
	public function withRuntimeConfig(array $config): static {
		$clone = clone $this;
		$clone->runtimeConfig = $config;
		return $clone;
	}

}
