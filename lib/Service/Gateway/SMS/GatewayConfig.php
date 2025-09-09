<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * Nextcloud - Two-factor Gateway
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Service\Gateway\IGatewayConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\IProvider;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\ProviderFactory;
use OCP\IConfig;

class GatewayConfig implements IGatewayConfig {
	public function __construct(
		private IConfig $config,
		private ProviderFactory $providerFactory,
	) {
	}

	public function getProvider(): IProvider {
		$providerName = $this->config->getAppValue(Application::APP_ID, 'sms_provider_name');
		if ($providerName === '') {
			throw new ConfigurationException();
		}

		return $this->providerFactory->getProvider($providerName);
	}

	public function setProvider(string $provider): void {
		$this->config->setAppValue(Application::APP_ID, 'sms_provider_name', $provider);
	}

	#[\Override]
	public function isComplete(): bool {
		try {
			$provider = $this->getProvider();
			return $provider->getConfig()->isComplete();
		} catch (ConfigurationException $ex) {
			return false;
		}
	}

	public function remove(): void {
		$this->getProvider()->getConfig()->remove();
	}
}
