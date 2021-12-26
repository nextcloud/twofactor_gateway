<?php

declare(strict_types=1);

/**
 * @author Daif Alazmi <daif@daif.net>
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

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use function array_intersect;
use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IConfig;

class CustomSMSConfig implements IProviderConfig {

	const expected = [
		'customsms_url',
		'customsms_method',
		'customsms_identifier',
		'customsms_message',
		'customsms_headers',
		'customsms_parameters',
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

	public function getUrl(): string {
		return $this->getOrFail('customsms_url');
	}

	public function setUrl(string $url) {
		$this->config->setAppValue(Application::APP_NAME, 'customsms_url', $url);
	}

	public function getMethod(): string {
		return $this->getOrFail('customsms_method');
	}

	public function setMethod(string $method) {
		$this->config->setAppValue(Application::APP_NAME, 'customsms_method', $method);
	}

	public function getIdentifier(): string {
		return $this->getOrFail('customsms_identifier');
	}

	public function setIdentifier(string $identifier) {
		$this->config->setAppValue(Application::APP_NAME, 'customsms_identifier', $identifier);
	}

	public function getMessage(): string {
		return $this->getOrFail('customsms_message');
	}

	public function setMessage(string $message) {
		$this->config->setAppValue(Application::APP_NAME, 'customsms_message', $message);
	}

	public function getHeaders(): string {
		return $this->getOrFail('customsms_headers');
	}

	public function setHeaders(string $headers) {
		$this->config->setAppValue(Application::APP_NAME, 'customsms_headers', $headers);
	}

	public function getParameters(): string {
		return $this->getOrFail('customsms_parameters');
	}

	public function setParameters(string $parameters) {
		$this->config->setAppValue(Application::APP_NAME, 'customsms_parameters', $parameters);
	}

	public function isComplete(): bool {
		$set = $this->config->getAppKeys(Application::APP_NAME);
		return count(array_intersect($set, self::expected)) === count(self::expected);
	}

	public function remove() {
		foreach(self::expected as $key) {
			$this->config->deleteAppValue(Application::APP_NAME, $key);
		}
	}
}
