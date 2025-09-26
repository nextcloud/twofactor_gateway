<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Gateway;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\IAppConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AGateway implements IGateway {
	use TConfigurable;
	public const SCHEMA = [];
	protected ?Settings $settings = null;

	public function __construct(
		public IAppConfig $appConfig,
	) {
	}

	/**
	 * @throws MessageTransmissionException
	 */
	#[\Override]
	abstract public function send(string $identifier, string $message, array $extra = []): void;

	#[\Override]
	public function isComplete(?Settings $settings = null): bool {
		if (!is_object($settings)) {
			$settings = $this->getSettings();
		}
		$savedKeys = $this->appConfig->getKeys(Application::APP_ID);
		$providerId = $settings->id ?? $this->getProviderId();
		$fields = [];
		foreach ($settings->fields as $field) {
			$fields[] = $providerId . '_' . $field->field;
		}
		$intersect = array_intersect($fields, $savedKeys);
		return count($intersect) === count($fields);
	}

	#[\Override]
	public function getSettings(): Settings {
		if ($this->settings !== null) {
			return $this->settings;
		}
		return $this->settings = $this->createSettings();
	}

	#[\Override]
	abstract public function cliConfigure(InputInterface $input, OutputInterface $output): int;

	#[\Override]
	public function remove(?Settings $settings = null): void {
		if (!is_object($settings)) {
			$settings = $this->getSettings();
		}
		foreach ($settings->fields as $field) {
			$method = 'delete' . $this->toCamel($field->field);
			$this->{$method}();
		}
	}

	protected function toCamel(string $field): string {
		return str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
	}

	#[\Override]
	public static function getProviderId(): string {
		$id = self::deriveIdFromFqcn(static::class);
		if ($id === null) {
			throw new \LogicException('Cannot derive gateway id from FQCN: ' . static::class);
		}
		return $id;
	}

	private static function deriveIdFromFqcn(string $fqcn): ?string {
		$prefix = 'OCA\\TwoFactorGateway\\Provider\\Channel\\';
		if (strncmp($fqcn, $prefix, strlen($prefix)) !== 0) {
			return null;
		}
		$rest = substr($fqcn, strlen($prefix));
		$sep = strpos($rest, '\\');
		if ($sep === false || substr($rest, $sep + 1) !== 'Gateway') {
			return null;
		}
		$type = substr($rest, 0, $sep);
		return $type !== '' ? strtolower($type) : null;
	}
}
