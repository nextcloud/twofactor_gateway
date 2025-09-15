<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS;

use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\Gateway\IGatewayConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\ProviderFactory;
use OCP\IUser;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class Gateway implements IGateway {

	public function __construct(
		public GatewayConfig $gatewayConfig,
		private ProviderFactory $providerFactory,
	) {
	}

	#[\Override]
	public function send(IUser $user, string $identifier, string $message, array $extra = []): void {
		$this->gatewayConfig->getProvider()->send($identifier, $message);
	}

	/**
	 * @return GatewayConfig
	 */
	#[\Override]
	public function getConfig(): IGatewayConfig {
		return $this->gatewayConfig;
	}

	public function getProvidersSchemas(): array {
		return $this->providerFactory->getSchemas();
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$schemas = $this->getProvidersSchemas();
		$names = array_column($schemas, 'name');

		$helper = new QuestionHelper();
		$choiceQuestion = new ChoiceQuestion('Please choose a SMS provider:', $names);
		$name = $helper->ask($input, $output, $choiceQuestion);
		$selectedIndex = array_search($name, $names);
		$schema = $schemas[$selectedIndex]['id'];

		$config = $this->gatewayConfig->getProvider($schema['id'])->config;

		foreach ($schema['fields'] as $field) {
			$id = $field['field'];
			$prompt = $field['prompt'];
			$defaultVal = $field['default'] ?? null;
			$optional = (bool)($field['optional'] ?? false);

			$answer = (string)$helper->ask($input, $output, new Question($prompt, $defaultVal));

			if ($optional && $answer === '') {
				$method = 'delete' . $this->toCamel($id);
				$config->{$method}();
				continue;
			}

			$method = 'set' . $this->toCamel($id);
			$config->{$method}($answer);
		}
		return 0;
	}

	private function toCamel(string $field): string {
		return str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
	}
}
