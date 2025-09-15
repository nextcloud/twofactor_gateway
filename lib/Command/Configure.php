<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Service\Gateway\Signal\Gateway as SignalGateway;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Gateway as SMSGateway;
use OCA\TwoFactorGateway\Service\Gateway\Telegram\Gateway as TelegramGateway;
use OCA\TwoFactorGateway\Service\Gateway\XMPP\Gateway as XMPPGateway;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Configure extends Command {

	public function __construct(
		private SignalGateway $signalGateway,
		private SMSGateway $smsGateway,
		private TelegramGateway $telegramGateway,
		private XMPPGateway $xmppGateway,
	) {
		parent::__construct('twofactorauth:gateway:configure');

		$this->addArgument(
			'gateway',
			InputArgument::REQUIRED,
			'The name of the gateway, e.g. sms, signal, telegram, xmpp, etc.'
		);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output) {
		$gatewayName = strtolower((string)$input->getArgument('gateway'));

		switch ($gatewayName) {
			case 'signal':
				return $this->signalGateway->cliConfigure($input, $output);
			case 'sms':
				return $this->smsGateway->cliConfigure($input, $output);
			case 'telegram':
				return $this->telegramGateway->cliConfigure($input, $output);
			case 'xmpp':
				return $this->xmppGateway->cliConfigure($input, $output);
			default:
				$output->writeln("<error>Invalid gateway $gatewayName</error>");
				return 1;
		}
	}
}
