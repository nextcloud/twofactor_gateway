<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use Exception;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Gateway\Factory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Remove extends Command {
	private array $ids = [];

	public function __construct(
		private Factory $gatewayFactory,
	) {
		parent::__construct('twofactorauth:gateway:remove');

		$fqcn = $this->gatewayFactory->getFqcnList();
		foreach ($fqcn as $fqcn) {
			$this->ids[] = $fqcn::getProviderId();
		}

		$this->addArgument(
			'gateway',
			InputArgument::OPTIONAL,
			'The name of the gateway: ' . implode(', ', $this->ids)
		);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output) {
		$gatewayName = strtolower((string)$input->getArgument('gateway'));
		if (!in_array($gatewayName, $this->ids, true)) {
			$helper = new QuestionHelper();
			$choiceQuestion = new ChoiceQuestion('Please choose a provider:', $this->ids);
			$gatewayName = $helper->ask($input, $output, $choiceQuestion);
		}

		try {
			/** @var AGateway */
			$gateway = $this->gatewayFactory->get($gatewayName);
		} catch (Exception $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return 1;
		}

		$gateway->remove();
		$output->writeln("Removed configuration for gateway $gatewayName");
		return 0;
	}
}
