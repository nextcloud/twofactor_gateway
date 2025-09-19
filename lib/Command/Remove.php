<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use Exception;
use OCA\TwoFactorGateway\Service\Gateway\Factory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Remove extends Command {

	public function __construct(
		private Factory $gatewayFactory,
	) {
		parent::__construct('twofactorauth:gateway:remove');

		$this->addArgument(
			'gateway',
			InputArgument::REQUIRED,
			'The name of the gateway, e.g. sms, signal, telegram, xmpp, etc.'
		);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output) {
		$gatewayName = strtolower((string)$input->getArgument('gateway'));

		try {
			$gateway = $this->gatewayFactory->getGateway($gatewayName);
		} catch (Exception $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return 1;
		}

		$gateway->remove();
		$output->writeln("Removed configuration for gateway $gatewayName");
		return 0;
	}
}
