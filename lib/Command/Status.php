<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Provider\Gateway\Factory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Status extends Command {
	public function __construct(
		private Factory $gatewayFactory,
	) {
		parent::__construct('twofactorauth:gateway:status');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output) {
		$signalConfigured = $this->gatewayFactory->getGateway('signal')->isComplete();
		$output->writeln('Signal gateway: ' . ($signalConfigured ? 'configured' : 'not configured'));

		$smsConfigured = $this->gatewayFactory->getGateway('sms')->isComplete();
		$output->writeln('SMS gateway: ' . ($smsConfigured ? 'configured' : 'not configured'));

		$telegramConfigured = $this->gatewayFactory->getGateway('telegram')->isComplete();
		$output->writeln('Telegram gateway: ' . ($telegramConfigured ? 'configured' : 'not configured'));

		$xmppConfigured = $this->gatewayFactory->getGateway('xmpp')->isComplete();
		$output->writeln('XMPP gateway: ' . ($xmppConfigured ? 'configured' : 'not configured'));
		return 0;
	}
}
