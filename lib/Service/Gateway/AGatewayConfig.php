<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IAppConfig;

class AGatewayConfig implements IGatewayConfig {
	protected const expected = [];

	public function __construct(
		public IAppConfig $config,
	) {
	}

	#[\Override]
	public function getOrFail(string $key): string {
		$val = $this->config->getValueString(Application::APP_ID, $key);
		if (empty($val)) {
			throw new ConfigurationException();
		}
		return $val;
	}

	#[\Override]
	public function isComplete(): bool {
		$set = $this->config->getKeys(Application::APP_ID);
		return count(array_intersect($set, static::expected)) === count(static::expected);
	}

	#[\Override]
	public function remove(): void {
		foreach (static::expected as $key) {
			$this->config->deleteKey(Application::APP_ID, $key);
		}
	}
}
