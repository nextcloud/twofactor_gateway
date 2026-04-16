<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Exception\InvalidProviderException;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\GoWhatsAppSessionMonitorJobManager;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Gateway\Factory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Configure extends Command {
	/** @var AGateway[] */
	private array $gateways = [];

	public function __construct(
		private Factory $gatewayFactory,
		private GoWhatsAppSessionMonitorJobManager $goWhatsAppSessionMonitorJobManager,
	) {
		parent::__construct('twofactorauth:gateway:configure');
		$this->setDescription('Configure a gateway for sending messages');

		$fqcnList = $this->gatewayFactory->getFqcnList();
		foreach ($fqcnList as $fqcn) {
			$gateway = $this->gatewayFactory->get($fqcn);
			$this->gateways[$gateway->getProviderId()] = $gateway;
		}

		$this->addArgument(
			'gateway',
			InputArgument::OPTIONAL,
			'The gateway id: ' . implode(', ', array_keys($this->gateways))
		);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$gatewayName = strtolower((string)$input->getArgument('gateway'));
		if (!array_key_exists($gatewayName, $this->gateways)) {
			if (count($this->gateways) === 0) {
				$output->writeln('<error>No gateway is available for configuration.</error>');
				return 1;
			}

			if (count($this->gateways) === 1) {
				$gateway = reset($this->gateways);
			} else {
				$helper = new QuestionHelper();
				$labelsById = GatewayChoiceFormatter::gatewayLabels($this->gateways);
				$choiceQuestion = new ChoiceQuestion('Please choose a provider:', array_values($labelsById));
				$selectedLabel = $helper->ask($input, $output, $choiceQuestion);
				$selectedGatewayId = GatewayChoiceFormatter::resolveIdFromLabel($labelsById, (string)$selectedLabel);
				if ($selectedGatewayId === null) {
					$output->writeln('<error>Invalid gateway selection.</error>');
					return Command::FAILURE;
				}
				$gateway = $this->gateways[$selectedGatewayId];
			}
		} else {
			$gateway = $this->gateways[$gatewayName];
		}

		try {
			$result = $gateway->cliConfigure($input, $output);
			$this->goWhatsAppSessionMonitorJobManager->sync();
			return $result;
		} catch (InvalidProviderException $e) {
			$output->writeln("<error>Invalid gateway $gatewayName</error>");
			return 1;
		}
	}
}
