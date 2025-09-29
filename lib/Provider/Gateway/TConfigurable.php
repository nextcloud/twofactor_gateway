<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Gateway;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IAppConfig;

trait TConfigurable {
	/**
	 * @return string|static
	 * @throws ConfigurationException
	 */
	public function __call(string $name, array $args) {
		if (!preg_match('/^(?<operation>get|set|delete)(?<field>[A-Z][A-Za-z0-9_]*)$/', $name, $matches)) {
			throw new ConfigurationException('Invalid method ' . $name);
		}
		$field = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $matches['field']));
		$key = $this->keyFromField($field);

		switch ($matches['operation']) {
			case 'get':
				$val = (string)$this->getAppConfig()->getValueString(Application::APP_ID, $key, '');
				if ($val === '') {
					throw new ConfigurationException('No value set for ' . $field);
				}
				return $val;

			case 'set':
				$this->getAppConfig()->setValueString(Application::APP_ID, $key, (string)($args[0] ?? ''));
				return $this;

			case 'delete':
				$this->getAppConfig()->deleteKey(Application::APP_ID, $key);
				return $this;
		}
		throw new ConfigurationException('Invalid operation ' . $matches['operation']);
	}

	/**
	 * @throws ConfigurationException
	 */
	private function keyFromField(string $fieldName): string {
		$settings = $this->getSettings();
		$fields = $settings->fields;
		foreach ($fields as $field) {
			if ($field->field === $fieldName) {
				return $settings->id . '_' . $fieldName;
			}
		}
		throw new ConfigurationException('Invalid configuration field: ' . $fieldName . ', check SCHEMA at ' . static::class);
	}

	/**
	 * @throws ConfigurationException
	 */
	private function getAppConfig(): IAppConfig {
		if (!isset($this->appConfig)) {
			throw new ConfigurationException('No app config set');
		}
		return $this->appConfig;
	}
}
