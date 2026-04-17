<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Gateway\Factory;
use OCA\TwoFactorGateway\Service\GatewayConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Status extends Command {
	public function __construct(
		private Factory $gatewayFactory,
		private GatewayConfigService $configService,
	) {
		parent::__construct('twofactorauth:gateway:status');
		$this->setDescription('Show the status of all configured gateways');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$fqcnList = $this->gatewayFactory->getFqcnList();
		foreach ($fqcnList as $fqcn) {
			/** @var AGateway */
			$gateway = $this->gatewayFactory->get($fqcn);
			$instances = $this->configService->listInstances($gateway);
			$isConfigured = false;
			foreach ($instances as $instance) {
				if (($instance['isComplete'] ?? false) === true) {
					$isConfigured = true;
					break;
				}
			}
			$settings = $gateway->getSettings();
			$output->writeln($settings->name . ': ' . ($isConfigured ? 'configured' : 'not configured'));
			$output->write(json_encode([
				'instances' => $instances,
			], JSON_PRETTY_PRINT), true, OutputInterface::VERBOSITY_VERBOSE);
		}
		return 0;
	}
}
