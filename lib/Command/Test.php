<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * Nextcloud - Two-factor Gateway
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\Gateway\Signal\Gateway as SignalGateway;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Gateway as SMSGateway;
use OCA\TwoFactorGateway\Service\Gateway\Telegram\Gateway as TelegramGateway;
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

	/** @var IUserManager */
	private $userManager;

	public function __construct(SignalGateway $signalGateway,
								SMSGateway $smsGateway,
								TelegramGateway $telegramGateway,
								IUserManager $userManager) {
		parent::__construct('twofactorauth:gateway:test');
		$this->signalGateway = $signalGateway;
		$this->smsGateway = $smsGateway;
		$this->telegramGateway = $telegramGateway;
		$this->userManager = $userManager;

		$this->addArgument(
			'uid',
			InputArgument::REQUIRED,
			'The user id'
		);
		$this->addArgument(
			'gateway',
			InputArgument::REQUIRED,
			'The name of the gateway, e.g. sms, signal, telegram, etc.'
		);
		$this->addArgument(
			'identifier',
			InputArgument::REQUIRED,
			'The identifier of the recipient , e.g. phone number, user id, etc.'
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$uid = $input->getArgument('uid');
		$user = $this->userManager->get($uid);
		if (is_null($user)) {
			$output->writeln("<error>Invalid UID</error>");
			return;
		}
		$gatewayName = $input->getArgument('gateway');
		$identifier = $input->getArgument('identifier');

		/** @var IGateway $gateway */
		$gateway = null;
		switch ($gatewayName) {
			case 'signal':
				$gateway = $this->signalGateway;
				break;
			case 'sms':
				$gateway = $this->smsGateway;
				break;
			case 'telegram':
				$gateway = $this->telegramGateway;
				break;
			default:
				$output->writeln("<error>Invalid gateway $gatewayName</error>");
				return;
		}

		$gateway->send($user, $identifier, 'Test');
		return 0;
	}
}
