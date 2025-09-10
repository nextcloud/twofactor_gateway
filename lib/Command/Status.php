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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Status extends Command {

	/** @var SignalGateway */
	private $signalGateway;

	/** @var SMSGateway */
	private $smsGateway;

	/** @var TelegramGateway */
	private $telegramGateway;

	/** @var XMPPGateway */
	private $xmppGateway;

	public function __construct(SignalGateway $signalGateway,
		SMSGateway $smsGateway,
		TelegramGateway $telegramGateway,
		XMPPGateway $xmppGateway) {
		parent::__construct('twofactorauth:gateway:status');
		$this->signalGateway = $signalGateway;
		$this->smsGateway = $smsGateway;
		$this->telegramGateway = $telegramGateway;
		$this->xmppGateway = $xmppGateway;
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output) {
		$signalConfigured = $this->signalGateway->getConfig()->isComplete();
		$output->writeln('Signal gateway: ' . ($signalConfigured ? 'configured' : 'not configured'));
		$smsConfigured = $this->smsGateway->getConfig()->isComplete();
		$output->writeln('SMS gateway: ' . ($smsConfigured ? 'configured' : 'not configured'));
		$telegramConfigured = $this->telegramGateway->getConfig()->isComplete();
		$output->writeln('Telegram gateway: ' . ($telegramConfigured ? 'configured' : 'not configured'));
		$xmppConfigured = $this->xmppGateway->getConfig()->isComplete();
		$output->writeln('XMPP gateway: ' . ($xmppConfigured ? 'configured' : 'not configured'));
		return 0;
	}
}
