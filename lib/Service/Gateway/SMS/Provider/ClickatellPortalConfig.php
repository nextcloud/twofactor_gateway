<?php

declare(strict_types=1);

/**
 * @author Christian Schrötter <cs@fnx.li>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author André Fondse <andre@hetnetwerk.org>
 *
 * Nextcloud - Two-factor Gateway for Telegram
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

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IConfig;

class ClickatellPortalConfig implements IProviderConfig {
	public const expected = [
		'clickatell_portal_apikey',
	];

	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	private function getOrFail(string $key): string {
		$val = $this->config->getAppValue(Application::APP_NAME, $key, null);
		if (is_null($val)) {
			throw new ConfigurationException();
		}
		return $val;
	}

	public function getApiKey(): string {
		return $this->getOrFail('clickatell_portal_apikey');
	}

	public function setApiKey(string $apiKey) {
		$this->config->setAppValue(Application::APP_NAME, 'clickatell_portal_apikey', $apiKey);
	}

	public function getFromNumber() /* ?string */
	{
		return $this->config->getAppValue(Application::APP_NAME, 'clickatell_portal_from', null);
	}

	public function setFromNumber(string $fromNumber) {
		$this->config->setAppValue(Application::APP_NAME, 'clickatell_portal_from', $fromNumber);
	}

	public function deleteFromNumber() {
		$this->config->deleteAppValue(Application::APP_NAME, 'clickatell_portal_from');
	}

	public function isComplete(): bool {
		$set = $this->config->getAppKeys(Application::APP_NAME);
		return count(array_intersect($set, self::expected)) === count(self::expected);
	}

	public function remove() {
		foreach (self::expected as $key) {
			$this->config->deleteAppValue(Application::APP_NAME, $key);
		}
	}
}
