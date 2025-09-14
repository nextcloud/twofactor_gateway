<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\IProvider;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\ProviderFactory;
use OCP\IAppConfig;

class GatewayConfig extends AGatewayConfig {
	public function __construct(
		public IAppConfig $config,
		private ProviderFactory $providerFactory,
	) {
	}

	public function getProvider(): IProvider {
		$providerName = $this->config->getValueString(Application::APP_ID, 'sms_provider_name');
		if ($providerName === '') {
			throw new ConfigurationException();
		}

		return $this->providerFactory->getProvider($providerName);
	}

	public function setProvider(string $provider): void {
		$this->config->setValueString(Application::APP_ID, 'sms_provider_name', $provider);
	}

	#[\Override]
	public function isComplete(): bool {
		try {
			$provider = $this->getProvider();
			return $provider->config->isComplete();
		} catch (ConfigurationException $ex) {
			return false;
		}
	}

	#[\Override]
	public function remove(): void {
		$this->getProvider()->config->remove();
	}
}
