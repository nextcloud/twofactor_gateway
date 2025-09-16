<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\IAppConfig;
use OCP\IUser;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AGateway implements IGateway {
	use TConfigurable;
	public const SCHEMA = [];

	public function __construct(
		public IAppConfig $appConfig,
	) {
	}

	/**
	 * @throws MessageTransmissionException
	 */
	#[\Override]
	abstract public function send(IUser $user, string $identifier, string $message, array $extra = []): void;

	#[\Override]
	public function isComplete(array $schema = []): bool {
		if (empty($schema)) {
			$schema = static::SCHEMA;
		}
		$set = $this->appConfig->getKeys(Application::APP_ID);
		$fields = array_column($schema['fields'], 'field');
		$fields = array_map(fn ($f) => $schema['id'] . '_' . $f, $fields);
		return count(array_intersect($set, $fields)) === count($fields);
	}

	#[\Override]
	abstract public function cliConfigure(InputInterface $input, OutputInterface $output): int;

	#[\Override]
	public function remove(array $schema = []): void {
		if (empty($schema)) {
			$schema = static::SCHEMA;
		}
		foreach ($schema['fields'] as $field) {
			$method = 'delete' . $this->toCamel($field['field']);
			$this->{$method}();
		}
	}

	protected function toCamel(string $field): string {
		return str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
	}
}
