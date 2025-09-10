<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christian Schrötter <cs@fnx.li>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IAppConfig;

class ClickatellPortalConfig implements IProviderConfig {
	public const expected = [
		'clickatell_portal_apikey',
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

	public function getApiKey(): string {
		return $this->getOrFail('clickatell_portal_apikey');
	}

	public function setApiKey(string $apiKey): void {
		$this->config->getValueString(Application::APP_ID, 'clickatell_portal_apikey', $apiKey);
	}

	public function getFromNumber(): string {
		return $this->config->getValueString(Application::APP_ID, 'clickatell_portal_from');
	}

	public function setFromNumber(string $fromNumber): void {
		$this->config->getValueString(Application::APP_ID, 'clickatell_portal_from', $fromNumber);
	}

	public function deleteFromNumber(): void {
		$this->config->deleteKey(Application::APP_ID, 'clickatell_portal_from');
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
