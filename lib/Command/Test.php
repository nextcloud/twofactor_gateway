<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\Gateway\Signal\Gateway as SignalGateway;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Gateway as SMSGateway;
use OCA\TwoFactorGateway\Service\Gateway\Telegram\Gateway as TelegramGateway;
use OCA\TwoFactorGateway\Service\Gateway\XMPP\Gateway as XMPPGateway;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Test extends Command {

	/** @var SignalGateway */
	private $signalGateway;

	/** @var SMSGateway */
	private $smsGateway;

	/** @var TelegramGateway */
	private $telegramGateway;

	/** @var XMPPGateway */
	private $xmppGateway;

	/** @var IUserManager */
	private $userManager;

	public function __construct(SignalGateway $signalGateway,
		SMSGateway $smsGateway,
		TelegramGateway $telegramGateway,
		XMPPGateway $xmppGateway,
		IUserManager $userManager) {
		parent::__construct('twofactorauth:gateway:test');
		$this->signalGateway = $signalGateway;
		$this->smsGateway = $smsGateway;
		$this->telegramGateway = $telegramGateway;
		$this->xmppGateway = $xmppGateway;
		$this->userManager = $userManager;

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
			$gateway = match (strtolower($gatewayName)) {
				'signal'   => $this->signalGateway,
				'sms'      => $this->smsGateway,
				'telegram' => $this->telegramGateway,
				'xmpp'     => $this->xmppGateway,
				default    => throw new \InvalidArgumentException("Invalid gateway $gatewayName"),
			};
		} catch (\InvalidArgumentException $e) {
			$output->writeln("<error>{$e->getMessage()}</error>");
			return 1;
		}

		$gateway->send($user, $identifier, 'Test');
		return 0;
	}
}
