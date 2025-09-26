<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram;

use OCA\TwoFactorGateway\Exception\InvalidProviderException;
use OCA\TwoFactorGateway\Provider\AFactory;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\AProvider;

/** @extends AFactory<AProvider> */
class Factory extends AFactory {
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
		return AProvider::class;
	}

	#[\Override]
	public function get(string $name): object {
		if (isset($this->instances[$name])) {
			return $this->instances[$name];
		}
		foreach ($this->getFqcnList() as $fqcn) {
			$instance = \OCP\Server::get($fqcn);
			if ($instance->getSettings()->id === $name) {
				$instance->setAppConfig(\OCP\Server::get(\OCP\IAppConfig::class));
				$this->instances[$name] = $instance;
				return $instance;
			}
		}
		throw new InvalidProviderException("Provider <$name> does not exist");
	}
}
