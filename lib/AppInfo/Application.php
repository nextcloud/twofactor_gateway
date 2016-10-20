<?php

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * Nextcloud - Two-factor SMS
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

namespace OCA\TwoFactor_Sms\AppInfo;

use OCP\AppFramework\App;
use OCP\IConfig;

class Application extends App {

	/**
	 * @param array $urlParams
	 */
	public function __construct($urlParams = []) {
		parent::__construct('twofactor_sms', $urlParams);

		$container = $this->getContainer();

		/* @var $config IConfig */
		$config = $container->query('\OCP\IConfig');
		$provider = $config->getAppValue('twofactor_sms', 'sms_provider', 'websms.de');

		$container->registerAlias('\OCA\TwoFactor_Sms\Service\ISmsService', $this->getSmsProviderClass($provider));
	}

	/**
	 * @param string $name
	 * @return string fully qualified class name
	 */
	private function getSmsProviderClass($name) {
		switch ($name) {
			case 'websms.de':
				return '\OCA\TwoFactor_Sms\Service\SmsProvider\WebSmsDe';
		}
	}

}
