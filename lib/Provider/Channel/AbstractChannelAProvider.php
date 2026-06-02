<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Provider\Gateway\TConfigurable;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\IAppConfig;

abstract class AbstractChannelAProvider {
	use TConfigurable;
	public IAppConfig $appConfig;
	protected ?Settings $settings = null;

	public function setAppConfig(IAppConfig $appConfig): void {
		$this->appConfig = $appConfig;
	}

	public function getSettings(): Settings {
		if ($this->settings !== null) {
			return $this->settings;
		}
		return $this->settings = $this->createSettings();
	}

	public static function idOverride(): ?string {
		return null;
	}

	public function getProviderId(): string {
		$settings = $this->getSettings();
		if (!empty($settings->id)) {
			return $settings->id;
		}
		$id = static::getIdFromProviderFqcn(static::class);
		if ($id === null) {
			throw new \LogicException('Cannot derive gateway id from FQCN: ' . static::class);
		}
		return $id;
	}

	abstract protected static function getDriverNamespacePrefix(): string;

	public function isComplete(): bool {
		$settings = $this->getSettings();
		$providerId = $settings->id ?? $this->getProviderId();
		foreach ($settings->fields as $field) {
			$key = self::keyFromFieldName($providerId, $field->field);
			if (!$this->appConfig->hasKey(Application::APP_ID, $key, true)) {
				return false;
			}
		}
		return true;
	}

	protected static function getIdFromProviderFqcn(string $fqcn): ?string {
		$prefix = static::getDriverNamespacePrefix();
		if (strncmp($fqcn, $prefix, strlen($prefix)) !== 0) {
			return null;
		}
		$type = substr($fqcn, strlen($prefix));
		if (strpos($type, '\\') !== false) {
			return null;
		}
		return $type !== '' ? strtolower($type) : null;
	}
}
