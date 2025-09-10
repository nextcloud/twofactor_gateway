<?php

declare(strict_types=1);

/**
 * @author Jordan Bieder <jordan.bieder@geduld.fr>
 *
 * @license GNU AGPL version 3 or any later version
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
use OCP\IAppConfig;
use function array_intersect;

class OvhConfig implements IProviderConfig {
	private const expected = [
		'ovh_application_key',
		'ovh_application_secret',
		'ovh_consumer_key',
		'ovh_endpoint',
		'ovh_account',
		'ovh_sender'
	];

	public function __construct(
		private IAppConfig $config,
	) {
	}

	private function getOrFail(string $key): string {
		$val = $this->config->getValueString(Application::APP_ID, $key);
		if (empty($val)) {
			throw new ConfigurationException();
		}
		return $val;
	}

	public function getApplicationKey(): string {
		return $this->getOrFail('ovh_application_key');
	}

	public function getApplicationSecret(): string {
		return $this->getOrFail('ovh_application_secret');
	}

	public function getConsumerKey(): string {
		return $this->getOrFail('ovh_consumer_key');
	}

	public function getEndpoint(): string {
		return $this->getOrFail('ovh_endpoint');
	}

	public function getAccount(): string {
		return $this->getOrFail('ovh_account');
	}

	public function getSender(): string {
		return $this->getOrFail('ovh_sender');
	}

	public function setApplicationKey(string $appKey): void {
		$this->config->getValueString(Application::APP_ID, 'ovh_application_key', $appKey);
	}

	public function setApplicationSecret(string $appSecret): void {
		$this->config->getValueString(Application::APP_ID, 'ovh_application_secret', $appSecret);
	}

	public function setConsumerKey(string $consumerKey): void {
		$this->config->getValueString(Application::APP_ID, 'ovh_consumer_key', $consumerKey);
	}

	public function setEndpoint(string $endpoint): void {
		$this->config->getValueString(Application::APP_ID, 'ovh_endpoint', $endpoint);
	}

	public function setAccount($account): void {
		$this->config->getValueString(Application::APP_ID, 'ovh_account', $account);
	}

	public function setSender($sender): void {
		$this->config->getValueString(Application::APP_ID, 'ovh_sender', $sender);
	}

	#[\Override]
	public function isComplete(): bool {
		$set = $this->config->getKeys(Application::APP_ID);
		return count(array_intersect($set, self::expected)) === count(self::expected);
	}

	#[\Override]
	public function remove() {
		foreach (self::expected as $key) {
			$this->config->deleteKey(Application::APP_ID, $key);
		}
	}
}
