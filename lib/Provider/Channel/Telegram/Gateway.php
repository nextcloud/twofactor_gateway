<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\AProvider;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\IAppConfig;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Gateway extends AGateway {

	public function __construct(
		public IAppConfig $appConfig,
		private Factory $telegramProviderFactory,
	) {
		parent::__construct($appConfig);
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		$this->getProvider()->send($identifier, $message);
	}

	#[\Override]
	final public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$namespaces = $this->telegramProviderFactory->getFqcnList();
		$names = [];
		$providers = [];
		foreach ($namespaces as $ns) {
			$provider = $this->telegramProviderFactory->get($ns);
			$providers[] = $provider;
			$names[] = $provider->getSettings()->name;
		}

		$helper = new QuestionHelper();
		$choiceQuestion = new ChoiceQuestion('Please choose a Telegram provider:', $names);
		$name = $helper->ask($input, $output, $choiceQuestion);
		$selectedIndex = array_search($name, $names);

		$providers[$selectedIndex]->cliConfigure($input, $output);
		return 0;
	}

	#[\Override]
	public function createSettings(): Settings {
		try {
			$settings = $this->getProvider()->getSettings();
		} catch (ConfigurationException) {
			$settings = new Settings(
				name: 'Telegram',
			);
		}
		return $settings;
	}

	#[\Override]
	public function isComplete(?Settings $settings = null): bool {
		if ($settings === null) {
			try {
				$provider = $this->getProvider();
			} catch (ConfigurationException) {
				return false;
			}
			$settings = $provider->getSettings();
		}
		return parent::isComplete($settings);
	}

	#[\Override]
	public function getConfiguration(?Settings $settings = null): array {
		try {
			$provider = $this->getProvider();
			$settings = $provider->getSettings();
			$config = parent::getConfiguration($settings);
			$config['provider'] = $settings->name;
			return $config;
		} catch (ConfigurationException|\Throwable $e) {
			$providers = [];
			foreach ($this->telegramProviderFactory->getFqcnList() as $fqcn) {
				$p = $this->telegramProviderFactory->get($fqcn);
				$p->setAppConfig($this->appConfig);
				$providerSettings = $p->getSettings();
				$providers[$providerSettings->name] = parent::getConfiguration($providerSettings);
			}
			return [
				'provider' => 'none',
				'available_providers' => $providers,
			];
		}
	}

	#[\Override]
	public function remove(?Settings $settings = null): void {
		foreach ($this->telegramProviderFactory->getFqcnList() as $fqcn) {
			$provider = $this->telegramProviderFactory->get($fqcn);
			$provider->setAppConfig($this->appConfig);
			$settings = $provider->getSettings();
			parent::remove($settings);
		}
	}

	public function getProvider(string $providerName = ''): AProvider {
		if ($providerName) {
			$this->setProvider($providerName);
		}
		$providerName = $this->appConfig->getValueString(Application::APP_ID, 'telegram_provider_name');
		if ($providerName === '') {
			throw new ConfigurationException();
		}

		return $this->telegramProviderFactory->get($providerName);
	}

	public function setProvider(string $provider): void {
		$this->appConfig->setValueString(Application::APP_ID, 'telegram_provider_name', $provider);
	}
}
