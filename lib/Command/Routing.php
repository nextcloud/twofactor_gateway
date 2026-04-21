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
	/** @var \OCA\TwoFactorGateway\Provider\Gateway\IGateway[] */
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
		$helper = $this->getQuestionHelper();

		$gateway = $this->resolveGateway($input, $output, $helper);
		if ($gateway === null) {
			return Command::FAILURE;
		}

		$instance = $this->resolveInstance($gateway, $input, $output, $helper);
		if ($instance === null) {
			return Command::FAILURE;
		}

		$priority = $this->resolvePriority($instance, $input, $output, $helper);
		if ($priority === null) {
			return Command::FAILURE;
		}

		$groupIds = $this->resolveGroupIds($instance, $input, $output, $helper);
		if ($groupIds === null) {
			return Command::FAILURE;
		}

		$updatedInstance = $this->configService->updateInstance(
			$gateway,
			$instance['id'],
			$instance['label'],
			$instance['config'],
			$groupIds,
			$priority,
		);

		$output->writeln(sprintf(
			'<info>Routing updated for instance <comment>%s</comment>: priority=%d, groups=[%s]</info>',
			$updatedInstance['id'],
			$updatedInstance['priority'],
			$updatedInstance['groupIds'] !== [] ? implode(', ', $updatedInstance['groupIds']) : 'none',
		));

		return Command::SUCCESS;
	}

	private function getQuestionHelper(): QuestionHelper {
		$helper = $this->getHelperSet()?->get('question');
		return $helper instanceof QuestionHelper ? $helper : new QuestionHelper();
	}

	private function resolveGateway(InputInterface $input, OutputInterface $output, QuestionHelper $helper): ?IGateway {
		$gatewayId = strtolower((string)$input->getArgument('gateway'));
		if (array_key_exists($gatewayId, $this->gateways)) {
			return $this->gateways[$gatewayId];
		}

		if ($this->gateways === []) {
			$output->writeln('<error>No gateway available.</error>');
			return null;
		}

		if (count($this->gateways) === 1) {
			$gateway = reset($this->gateways);
			if ($gateway === false) {
				$output->writeln('<error>No gateway available.</error>');
				return null;
			}

			return $gateway;
		}

		$labelsById = GatewayChoiceFormatter::gatewayLabels($this->gateways);
		$selectedLabel = $helper->ask($input, $output, new ChoiceQuestion('Gateway:', array_values($labelsById)));
		$selectedGatewayId = GatewayChoiceFormatter::resolveIdFromLabel($labelsById, (string)$selectedLabel);
		if ($selectedGatewayId === null) {
			$output->writeln('<error>Invalid gateway selection.</error>');
			return null;
		}

		return $this->gateways[$selectedGatewayId];
	}

	/**
	 * @return array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}|null
	 */
	private function resolveInstance(IGateway $gateway, InputInterface $input, OutputInterface $output, QuestionHelper $helper): ?array {
		$instances = $this->configService->listInstances($gateway);
		if ($instances === []) {
			$output->writeln('<error>No configured instances for gateway ' . $gateway->getProviderId() . '. Run twofactorauth:gateway:configure first.</error>');
			return null;
		}

		$instanceById = [];
		foreach ($instances as $instance) {
			$instanceById[$instance['id']] = $instance;
		}

		$instanceId = (string)$input->getArgument('instance');
		if (array_key_exists($instanceId, $instanceById)) {
			return $instanceById[$instanceId];
		}

		$labelsById = GatewayChoiceFormatter::instanceLabels($instances);
		$selectedLabel = $helper->ask($input, $output, new ChoiceQuestion('Instance:', array_values($labelsById)));
		$selectedInstanceId = GatewayChoiceFormatter::resolveIdFromLabel($labelsById, (string)$selectedLabel);
		if ($selectedInstanceId === null) {
			$output->writeln('<error>Invalid instance selection.</error>');
			return null;
		}

		return $instanceById[$selectedInstanceId] ?? null;
	}

	/**
	 * @param array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int} $instance
	 */
	private function resolvePriority(array $instance, InputInterface $input, OutputInterface $output, QuestionHelper $helper): ?int {
		$priorityRaw = $input->getOption('priority');
		if ($priorityRaw === null) {
			$priorityRaw = $helper->ask(
				$input,
				$output,
				new Question(sprintf('Routing priority [current: %d, 0 = no preference, higher runs first]: ', $instance['priority']), (string)$instance['priority']),
			);
		}

		$priority = $this->parsePriority($priorityRaw);
		if ($priority !== null) {
			return $priority;
		}

		$output->writeln('<error>Priority must be an integer.</error>');
		return null;
	}

	/**
	 * @param array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int} $instance
	 * @return list<string>|null
	 */
	private function resolveGroupIds(array $instance, InputInterface $input, OutputInterface $output, QuestionHelper $helper): ?array {
		$allGroupIds = $this->loadAvailableGroupIds();
		$groupsRaw = $input->getOption('groups');
		if ($groupsRaw === null) {
			$currentGroupsList = $instance['groupIds'] !== [] ? implode(', ', $instance['groupIds']) : 'none';
			$output->writeln('Available groups: ' . implode(', ', $allGroupIds));
			$groupsRaw = $helper->ask(
				$input,
				$output,
				new Question(sprintf('Restrict to groups — comma-separated ids [current: %s, empty = no restriction]: ', $currentGroupsList), implode(',', $instance['groupIds'])),
			);
		}

		$groupIds = self::parseGroupIds((string)$groupsRaw);
		$unknownGroups = array_values(array_diff($groupIds, $allGroupIds));
		if ($unknownGroups !== []) {
			$output->writeln('<error>Unknown group(s): ' . implode(', ', $unknownGroups) . '</error>');
			return null;
		}

		return $groupIds;
	}

	/**
	 * @return list<string>
	 */
	private function loadAvailableGroupIds(): array {
		$allGroups = $this->groupManager->search('');
		$allGroupIds = array_map(static fn ($group): string => $group->getGID(), $allGroups);
		sort($allGroupIds);
		return $allGroupIds;
	}

	private function parsePriority(mixed $priorityRaw): ?int {
		if (filter_var((string)$priorityRaw, FILTER_VALIDATE_INT) === false) {
			return null;
		}

		return (int)$priorityRaw;
	}

	/**
	 * @return list<string>
	 */
	private static function parseGroupIds(string $groupsRaw): array {
		return array_values(array_filter(array_map('trim', explode(',', $groupsRaw))));
	}
}
