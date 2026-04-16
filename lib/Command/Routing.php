<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Provider\Gateway\Factory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\GatewayConfigService;
use OCP\IGroupManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class Routing extends Command {
	/** @var IGateway[] */
	private array $gateways = [];

	public function __construct(
		private Factory $gatewayFactory,
		private GatewayConfigService $configService,
		private IGroupManager $groupManager,
	) {
		parent::__construct('twofactorauth:gateway:routing');

		foreach ($this->gatewayFactory->getFqcnList() as $fqcn) {
			$gateway = $this->gatewayFactory->get($fqcn);
			$this->gateways[$gateway->getProviderId()] = $gateway;
		}

		$this->setDescription('Configure routing priority and group restrictions for a gateway instance.');

		$this->addArgument('gateway', InputArgument::OPTIONAL, 'Gateway id: ' . implode(', ', array_keys($this->gateways)));
		$this->addArgument('instance', InputArgument::OPTIONAL, 'Instance id (run without arguments to list available instances)');
		$this->addOption('priority', null, InputOption::VALUE_REQUIRED, 'Routing priority (integer; higher runs first among matched instances)');
		$this->addOption('groups', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of group ids to restrict this instance to (empty = no restriction)');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$helper = new QuestionHelper();

		// ── 1. Resolve gateway ────────────────────────────────────────────────
		$gatewayId = strtolower((string)$input->getArgument('gateway'));
		if (!array_key_exists($gatewayId, $this->gateways)) {
			if (count($this->gateways) === 0) {
				$output->writeln('<error>No gateway available.</error>');
				return Command::FAILURE;
			}
			if (count($this->gateways) === 1) {
				$gateway = reset($this->gateways);
			} else {
				$labelsById = GatewayChoiceFormatter::gatewayLabels($this->gateways);
				$selectedLabel = $helper->ask($input, $output, new ChoiceQuestion('Gateway:', array_values($labelsById)));
				$gatewayId = GatewayChoiceFormatter::resolveIdFromLabel($labelsById, (string)$selectedLabel) ?? '';
				if ($gatewayId === '') {
					$output->writeln('<error>Invalid gateway selection.</error>');
					return Command::FAILURE;
				}
				$gateway = $this->gateways[$gatewayId];
			}
		} else {
			$gateway = $this->gateways[$gatewayId];
		}

		// ── 2. Resolve instance ───────────────────────────────────────────────
		$instances = $this->configService->listInstances($gateway);
		if ($instances === []) {
			$output->writeln('<error>No configured instances for gateway ' . $gateway->getProviderId() . '. Run twofactorauth:gateway:configure first.</error>');
			return Command::FAILURE;
		}

		$instanceId = (string)$input->getArgument('instance');
		$instanceById = [];
		foreach ($instances as $instance) {
			$instanceById[$instance['id']] = $instance;
		}

		if (!array_key_exists($instanceId, $instanceById)) {
			$labelsById = GatewayChoiceFormatter::instanceLabels($instances);
			$selectedLabel = $helper->ask($input, $output, new ChoiceQuestion('Instance:', array_values($labelsById)));
			$instanceId = GatewayChoiceFormatter::resolveIdFromLabel($labelsById, (string)$selectedLabel) ?? '';
			if ($instanceId === '') {
				$output->writeln('<error>Invalid instance selection.</error>');
				return Command::FAILURE;
			}
		}

		$instance = $instanceById[$instanceId];

		// ── 3. Resolve priority ───────────────────────────────────────────────
		$priorityRaw = $input->getOption('priority');
		if ($priorityRaw === null) {
			$priorityRaw = $helper->ask(
				$input,
				$output,
				new Question(sprintf('Routing priority [current: %d, 0 = no preference, higher runs first]: ', $instance['priority']), (string)$instance['priority']),
			);
		}

		if (!is_numeric($priorityRaw) || (int)$priorityRaw != $priorityRaw) {
			$output->writeln('<error>Priority must be an integer.</error>');
			return Command::FAILURE;
		}

		$priority = (int)$priorityRaw;

		// ── 4. Resolve groups ─────────────────────────────────────────────────
		$groupsRaw = $input->getOption('groups');

		$allGroups = $this->groupManager->search('');
		$allGroupIds = array_map(static fn ($g) => $g->getGID(), $allGroups);
		sort($allGroupIds);

		if ($groupsRaw === null) {
			$currentGroupsList = $instance['groupIds'] !== [] ? implode(', ', $instance['groupIds']) : 'none';
			$output->writeln('Available groups: ' . implode(', ', $allGroupIds));
			$groupsRaw = $helper->ask(
				$input,
				$output,
				new Question(sprintf('Restrict to groups — comma-separated ids [current: %s, empty = no restriction]: ', $currentGroupsList), implode(',', $instance['groupIds'])),
			);
		}

		$groupIds = array_values(array_filter(array_map('trim', explode(',', (string)$groupsRaw))));

		$unknownGroups = array_diff($groupIds, $allGroupIds);
		if ($unknownGroups !== []) {
			$output->writeln('<error>Unknown group(s): ' . implode(', ', $unknownGroups) . '</error>');
			return Command::FAILURE;
		}

		// ── 5. Save ───────────────────────────────────────────────────────────
		$this->configService->updateInstance(
			$gateway,
			$instanceId,
			$instance['label'],
			$instance['config'],
			$groupIds,
			$priority,
		);

		$output->writeln(sprintf(
			'<info>Routing updated for instance <comment>%s</comment>: priority=%d, groups=[%s]</info>',
			$instanceId,
			$priority,
			$groupIds !== [] ? implode(', ', $groupIds) : 'none',
		));

		return Command::SUCCESS;
	}
}
