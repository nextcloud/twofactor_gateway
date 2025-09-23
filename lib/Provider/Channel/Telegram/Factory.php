<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram;

use OCA\TwoFactorGateway\Exception\InvalidProviderException;
use OCA\TwoFactorGateway\Provider\AFactory;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\IProvider;

class Factory extends AFactory {
	/** @var array<string,IProvider> */
	protected array $instances = [];
	#[\Override]
	protected function getPrefix(): string {
		return 'OCA\\TwoFactorGateway\\Provider\\Channel\\Telegram\\Provider\\Drivers\\';
	}

	#[\Override]
	protected function getSuffix(): string {
		return '';
	}

	#[\Override]
	protected function getBaseClass(): string {
		return IProvider::class;
	}

	#[\Override]
	public function isValid(string $fqcn): bool {
		return defined("$fqcn::SCHEMA")
			&& is_array($fqcn::SCHEMA);
	}

	#[\Override]
	public function get(string $name): IProvider {
		if (isset($this->instances[$name])) {
			return $this->instances[$name];
		}
		foreach ($this->getFqcnList() as $fqcn) {
			if ($fqcn::getProviderId() === $name) {
				$instance = \OCP\Server::get($fqcn);
				$instance->setAppConfig(\OCP\Server::get(\OCP\IAppConfig::class));
				$this->instances[$name] = $instance;
				return $instance;
			}
		}
		throw new InvalidProviderException("Provider <$name> does not exist");
	}
}
