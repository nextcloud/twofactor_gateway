<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Exception\InvalidProviderException;
use OCA\TwoFactorGateway\Provider\Gateway\Factory;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Test extends Command {
	public function __construct(
		private IUserManager $userManager,
		private Factory $gatewayFactory,
	) {
		parent::__construct('twofactorauth:gateway:test');

		$fqcn = $this->gatewayFactory->getFqcnList();
		$gateways = [];
		foreach ($fqcn as $fqcn) {
			$gateway = $this->gatewayFactory->get($fqcn);
			$gateways[$gateway->getProviderId()] = $gateway;
		}

		$this->addArgument(
			'gateway',
			InputArgument::REQUIRED,
			'The name of the gateway: ' . implode(', ', array_keys($gateways))
		);
		$this->addArgument(
			'identifier',
			InputArgument::REQUIRED,
			'The identifier of the recipient , e.g. phone number, user id, etc.'
		);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output) {
		$gatewayName = $input->getArgument('gateway');
		$identifier = $input->getArgument('identifier');

		try {
			$gateway = $this->gatewayFactory->get($gatewayName);
			if (!$gateway->isComplete()) {
				$output->writeln("<error>Gateway $gatewayName is not configured</error>");
				return 1;
			}
		} catch (InvalidProviderException $e) {
			$output->writeln("<error>{$e->getMessage()}</error>");
			return 1;
		}

		$gateway->send($identifier, 'Test', ['code' => '123456']);
		return 0;
	}
}
