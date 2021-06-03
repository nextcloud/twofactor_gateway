<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Simon Spannagel <simonspa@kth.se>
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

use OCA\TwoFactorGateway\Service\Gateway\Signal\Gateway as SignalGateway;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Gateway as SMSGateway;
use OCA\TwoFactorGateway\Service\Gateway\Telegram\Gateway as TelegramGateway;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Remove extends Command {

	/** @var SignalGateway */
	private $signalGateway;

	/** @var SMSGateway */
	private $smsGateway;

	/** @var TelegramGateway */
	private $telegramGateway;

	public function __construct(SignalGateway $signalGateway,
								SMSGateway $smsGateway,
								TelegramGateway $telegramGateway) {
		parent::__construct('twofactorauth:gateway:remove');
		$this->signalGateway = $signalGateway;
		$this->smsGateway = $smsGateway;
		$this->telegramGateway = $telegramGateway;

		$this->addArgument(
			'gateway',
			InputArgument::REQUIRED,
			'The name of the gateway, e.g. sms, signal, telegram, etc.'
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$gatewayName = $input->getArgument('gateway');

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
				return 1;
		}

		$gateway->getConfig()->remove();
		$output->writeln("Removed configuration for gateway $gatewayName");
		return 0;
	}
}
