<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Exception\InvalidProviderException;
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
	) {
		parent::__construct('twofactorauth:gateway:configure');

		$fqcnList = $this->gatewayFactory->getFqcnList();
		foreach ($fqcnList as $fqcn) {
			$gateway = $this->gatewayFactory->get($fqcn);
			$this->gateways[$gateway->getSettings()->id] = $gateway;
		}

		$this->addArgument(
			'gateway',
			InputArgument::OPTIONAL,
			'The name of the gateway: ' . implode(', ', array_keys($this->gateways))
		);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output) {
		$gatewayName = strtolower((string)$input->getArgument('gateway'));
		if (!array_key_exists($gatewayName, $this->gateways)) {
			$helper = new QuestionHelper();
			$choiceQuestion = new ChoiceQuestion('Please choose a provider:', array_keys($this->gateways));
			$selectedIndex = $helper->ask($input, $output, $choiceQuestion);
			$gateway = $this->gateways[$selectedIndex];
		}

		try {
			return $gateway->cliConfigure($input, $output);
		} catch (InvalidProviderException $e) {
			$output->writeln("<error>Invalid gateway $gatewayName</error>");
			return 1;
		}
	}
}
