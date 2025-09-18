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

trait TConfigurable {
	/**
	 * @return string|static
	 * @throws ConfigurationException
	 */
	public function __call(string $name, array $args) {
		if (!preg_match('/^(get|set|delete)([A-Z][A-Za-z0-9_]*)$/', $name, $matches)) {
			throw new ConfigurationException();
		}
		$op = $matches[1];
		$alias = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $matches[2]));
		$key = $this->keyFromAlias($alias);

		switch ($op) {
			case 'get':
				$val = (string)$this->getAppConfig()->getValueString(Application::APP_ID, $key, '');
				if ($val === '') {
					throw new ConfigurationException();
				}
				return $val;

			case 'set':
				$this->getAppConfig()->setValueString(Application::APP_ID, $key, (string)($args[0] ?? ''));
				return $this;

			case 'delete':
				$this->getAppConfig()->deleteKey(Application::APP_ID, $key);
				return $this;
		}
		throw new ConfigurationException();
	}

	/**
	 * @throws ConfigurationException
	 */
	private function keyFromAlias(string $alias): string {
		$fields = array_column($this->getSchemaFields(), 'field');
		if (!in_array($alias, $fields, true)) {
			throw new ConfigurationException();
		}
		return $this->getProviderId() . '_' . $alias;
	}

	/**
	 * @throws ConfigurationException
	 */
	private function getAppConfig(): IAppConfig {
		if (!isset($this->appConfig)) {
			throw new ConfigurationException();
		}
		return $this->appConfig;
	}

	/**
	 * @return array
	 * @throws ConfigurationException
	 */
	protected function getSchemaFields(): array {
		return static::getSchema()['fields'];
	}

	/**
	 * @return array
	 * @throws ConfigurationException
	 */
	public static function getSchema(): array {
		if (!defined(static::class . '::SCHEMA')) {
			throw new ConfigurationException();
		}
		return static::SCHEMA;
	}
}
