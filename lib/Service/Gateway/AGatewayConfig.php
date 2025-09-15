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

abstract class AGatewayConfig implements IGatewayConfig {
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

	public function __call(string $name, array $args) {
		if (!preg_match('/^(get|set)([A-Z][A-Za-z0-9_]*)$/', $name, $matches)) {
			throw new ConfigurationException();
		}
		$op = $matches[1];
		$alias = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $matches[2]));
		$key = $this->keyFromAlias($alias);

		switch ($op) {
			case 'get':
				$val = (string)$this->config->getValueString(Application::APP_ID, $key, '');
				if ($val === '') {
					throw new ConfigurationException();
				}
				return $val;

			case 'set':
				$this->config->setValueString(Application::APP_ID, $key, (string)($args[0] ?? ''));
				return $this;
		}
	}

	final protected function keyFromAlias(string $alias): string {
		if (!in_array($alias, static::expected)) {
			throw new ConfigurationException();
		}
		return $this->providerId() . '_' . $alias;
	}

	#[\Override]
	abstract public static function providerId(): string;
}
