<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\IProvider;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCP\IAppConfig;
use OCP\IUser;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class Gateway extends AGateway {

	public function __construct(
		public IAppConfig $appConfig,
		private Factory $providerFactory,
	) {
		parent::__construct($appConfig);
	}

	#[\Override]
	public function send(IUser $user, string $identifier, string $message, array $extra = []): void {
		$this->getProvider()->send($identifier, $message);
	}

	#[\Override]
	final public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$namespaces = $this->providerFactory->getFqcnList();
		foreach ($namespaces as $ns) {
			$schemas[] = $ns::SCHEMA;
		}
		$names = array_column($schemas, 'name');

		$helper = new QuestionHelper();
		$choiceQuestion = new ChoiceQuestion('Please choose a SMS provider:', $names);
		$name = $helper->ask($input, $output, $choiceQuestion);
		$selectedIndex = array_search($name, $names);
		$schema = $schemas[$selectedIndex];

		$provider = $this->getProvider($namespaces[$selectedIndex]::getProviderId());

		foreach ($schema['fields'] as $field) {
			$id = $field['field'];
			$prompt = $field['prompt'] . ' ';
			$defaultVal = $field['default'] ?? null;
			$optional = (bool)($field['optional'] ?? false);

			$answer = (string)$helper->ask($input, $output, new Question($prompt, $defaultVal));

			if ($optional && $answer === '') {
				$method = 'delete' . $this->toCamel($id);
				$provider->{$method}();
				continue;
			}

			$method = 'set' . $this->toCamel($id);
			$provider->{$method}($answer);
		}
		return 0;
	}

	#[\Override]
	public function isComplete(array $schema = []): bool {
		if (empty($schema)) {
			try {
				$provider = $this->getProvider();
			} catch (ConfigurationException) {
				return false;
			}
			$schema = $provider::SCHEMA;
		}
		return parent::isComplete($schema);
	}

	#[\Override]
	public function remove(array $schema = []): void {
		if (empty($schema)) {
			$schema = static::SCHEMA;
		}
		parent::remove($schema);
	}

	public function getProvider(string $providerName = ''): IProvider {
		if ($providerName) {
			$this->setProvider($providerName);
		}
		$providerName = $this->appConfig->getValueString(Application::APP_ID, 'sms_provider_name');
		if ($providerName === '') {
			throw new ConfigurationException();
		}

		return $this->providerFactory->get($providerName);
	}

	public function setProvider(string $provider): void {
		$this->appConfig->setValueString(Application::APP_ID, 'sms_provider_name', $provider);
	}
}
