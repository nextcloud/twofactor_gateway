<?php

declare(strict_types = 1);

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

namespace OCA\TwoFactorGateway\AppInfo;

use Exception;
use OCA\TwoFactorGateway\Service\ISmsService;
use OCA\TwoFactorGateway\Service\Gateway\PlaySMSGateway;
use OCA\TwoFactorGateway\Service\Gateway\SignalGateway;
use OCA\TwoFactorGateway\Service\Gateway\TelegramGateway;
use OCA\TwoFactorGateway\Service\Gateway\TestGateway;
use OCA\TwoFactorGateway\Service\Gateway\WebSmsGateway;
use OCP\AppFramework\App;
use OCP\IConfig;

class Application extends App {

	const APP_NAME = 'twofactor_gateway';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_NAME, $urlParams);

		$container = $this->getContainer();

		/* @var $config IConfig */
		$config = $container->query(IConfig::class);
		$provider = $config->getAppValue('twofactor_gateway', 'sms_provider', 'websms.de');

		$container->registerAlias(ISmsService::class, $this->getSmsProviderClass($provider));
	}

	private function getSmsProviderClass(string $name): string {
		switch ($name) {
			case 'playsms':
				return PlaySMSGateway::class;
			case 'signal':
				return SignalGateway::class;
			case 'telegram':
				return TelegramGateway::class;
			case 'test':
				return TestGateway::class;
			case 'websms.de':
				return WebSmsGateway::class;
		}
		throw new Exception('invalid configuration for twofactor_gateway app');
	}

}
