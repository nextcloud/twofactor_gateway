<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Exception\InvalidProviderException;
use OCA\TwoFactorGateway\Service\Gateway\Factory;
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

		$this->addArgument(
			'uid',
			InputArgument::REQUIRED,
			'The user id'
		);
		$this->addArgument(
			'gateway',
			InputArgument::REQUIRED,
			'The name of the gateway, e.g. sms, signal, telegram, xmpp, etc.'
		);
		$this->addArgument(
			'identifier',
			InputArgument::REQUIRED,
			'The identifier of the recipient , e.g. phone number, user id, etc.'
		);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output) {
		$uid = $input->getArgument('uid');
		$user = $this->userManager->get($uid);
		if (is_null($user)) {
			$output->writeln('<error>Invalid UID</error>');
			return 1;
		}
		$gatewayName = $input->getArgument('gateway');
		$identifier = $input->getArgument('identifier');

		try {
			$gateway = $this->gatewayFactory->getGateway($gatewayName);
		} catch (InvalidProviderException $e) {
			$output->writeln("<error>{$e->getMessage()}</error>");
			return 1;
		}

		$gateway->send($user, $identifier, 'Test', ['code' => '123456']);
		return 0;
	}
}
