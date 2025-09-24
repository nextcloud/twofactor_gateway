<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\IProvider;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCP\IAppConfig;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Gateway extends AGateway {
	public const SCHEMA = [
		'name' => 'Telegram',
	];

	public function __construct(
		public IAppConfig $appConfig,
		private Factory $providerFactory,
	) {
		parent::__construct($appConfig);
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		$this->getProvider()->send($identifier, $message);
	}

	#[\Override]
	final public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$namespaces = $this->providerFactory->getFqcnList();
		$schemas = [];
		foreach ($namespaces as $ns) {
			$schemas[] = $ns::SCHEMA;
		}
		$names = array_column($schemas, 'name');

		$helper = new QuestionHelper();
		$choiceQuestion = new ChoiceQuestion('Please choose a Telegram provider:', $names);
		$name = $helper->ask($input, $output, $choiceQuestion);
		$selectedIndex = array_search($name, $names);
		$schema = $schemas[$selectedIndex];

		$provider = $this->getProvider($namespaces[$selectedIndex]::getProviderId());

		$provider->cliConfigure($input, $output, $provider, $schema);
		return 0;
	}

	#[\Override]
	public function getSettings(): array {
		try {
			$provider = $this->getProvider();
		} catch (ConfigurationException) {
			return static::SCHEMA;
		}
		return $provider::SCHEMA;
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
		$providerName = $this->appConfig->getValueString(Application::APP_ID, 'telegram_provider_name');
		if ($providerName === '') {
			throw new ConfigurationException();
		}

		return $this->providerFactory->get($providerName);
	}

	public function setProvider(string $provider): void {
		$this->appConfig->setValueString(Application::APP_ID, 'telegram_provider_name', $provider);
	}
}
