<?php

declare(strict_types=1);

/**
 * @author Jordan Bieder <jordan.bieder@geduld.fr>
 *
 * Nextcloud - Two-factor Gateway for Ovh
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

use function array_intersect;
use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IConfig;

class OvhConfig implements IProviderConfig {
	private const expected = [
		'ovh_application_key',
		'ovh_application_secret',
		'ovh_consumer_key',
		'ovh_endpoint',
		'ovh_account',
		'ovh_sender'
	];

	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	private function getOrFail(string $key): string {
		$val = $this->config->getAppValue(Application::APP_ID, $key, null);
		if (is_null($val)) {
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

	public function setApplicationKey(string $appKey) {
		$this->config->setAppValue(Application::APP_ID, 'ovh_application_key', $appKey);
	}

	public function setApplicationSecret(string $appSecret) {
		$this->config->setAppValue(Application::APP_ID, 'ovh_application_secret', $appSecret);
	}

	public function setConsumerKey(string $consumerKey) {
		$this->config->setAppValue(Application::APP_ID, 'ovh_consumer_key', $consumerKey);
	}

	public function setEndpoint(string $endpoint) {
		$this->config->setAppValue(Application::APP_ID, 'ovh_endpoint', $endpoint);
	}

	public function setAccount($account) {
		$this->config->setAppValue(Application::APP_ID, 'ovh_account', $account);
	}

	public function setSender($sender) {
		$this->config->setAppValue(Application::APP_ID, 'ovh_sender', $sender);
	}

	public function isComplete(): bool {
		$set = $this->config->getAppKeys(Application::APP_ID);
		return count(array_intersect($set, self::expected)) === count(self::expected);
	}

	public function remove() {
		foreach (self::expected as $key) {
			$this->config->deleteAppValue(Application::APP_ID, $key);
		}
	}
}
