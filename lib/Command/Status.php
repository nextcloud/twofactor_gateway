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

use OCA\TwoFactorGateway\Service\Gateway\Signal\Gateway as SignalGateway;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Gateway as SMSGateway;
use OCA\TwoFactorGateway\Service\Gateway\Telegram\Gateway as TelegramGateway;
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

	public function __construct(SignalGateway $signalGateway,
								SMSGateway $smsGateway,
								TelegramGateway $telegramGateway) {
		parent::__construct('twofactorauth:gateway:status');
		$this->signalGateway = $signalGateway;
		$this->smsGateway = $smsGateway;
		$this->telegramGateway = $telegramGateway;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$signalConfigured = $this->signalGateway->getConfig()->isComplete();
		$output->writeln('Signal gateway: ' . ($signalConfigured ? 'configured' : 'not configured'));
		$smsConfigured = $this->smsGateway->getConfig()->isComplete();
		$output->writeln('SMS gateway: ' . ($smsConfigured ? 'configured' : 'not configured'));
		$telegramConfigured = $this->telegramGateway->getConfig()->isComplete();
		$output->writeln('Telegram gateway: ' . ($telegramConfigured ? 'configured' : 'not configured'));
		return 0;
	}
}
