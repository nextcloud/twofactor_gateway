<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp;

use OCA\TwoFactorGateway\Exception\InvalidProviderException;
use OCA\TwoFactorGateway\Provider\AFactory;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;

/** @extends AFactory<AGateway> */
class Factory extends AFactory {
	/** @var array<AGateway> */
	private array $instancesByFqcn = [];

	#[\Override]
	protected function getPrefix(): string {
		return 'OCA\\TwoFactorGateway\\Provider\\Channel\\WhatsApp\\Provider\\Drivers\\';
	}

	#[\Override]
	protected function getSuffix(): string {
		return '\\Gateway';
	}

	#[\Override]
	protected function getBaseClass(): string {
		return AGateway::class;
	}

	#[\Override]
	public function get(string $name): object {
		if (isset($this->instancesByFqcn[$name])) {
			return $this->instancesByFqcn[$name];
		}
		if (isset($this->instances[$name])) {
			return $this->instances[$name];
		}

		foreach ($this->getFqcnList() as $fqcn) {
			$instance = \OCP\Server::get($fqcn);
			$settings = $instance->getSettings();
			if ($fqcn === $name || $settings->id === $name) {
				$this->instances[$settings->id] = $instance;
				$this->instancesByFqcn[$fqcn] = $instance;
				return $instance;
			}
		}

		throw new InvalidProviderException("Provider <$name> does not exist");
	}
}
