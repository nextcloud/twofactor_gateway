<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel;

use OCA\TwoFactorGateway\Exception\InvalidProviderException;
use OCA\TwoFactorGateway\Provider\AFactory;

/**
 * @template T of object
 * @extends AFactory<T>
 */
abstract class AbstractCatalogFactory extends AFactory {
	/** @var array<string, T> */
	private array $instancesByFqcn = [];

	/**
	 * Returns the instance cache key to use for `$this->instances` when a
	 * provider is first resolved. SMS caches by `$settings->id`, Telegram by
	 * the driver name passed to `get()`.
	 *
	 * @param T $instance
	 */
	abstract protected function resolveInstanceCacheKey(string $name, object $instance): string;

	#[\Override]
	public function get(string $name): object {
		if (isset($this->instancesByFqcn[$name])) {
			return $this->instancesByFqcn[$name];
		}
		if (isset($this->instances[$name])) {
			return $this->instances[$name];
		}
		foreach ($this->getFqcnList() as $fqcn) {
			/** @var T $instance */
			$instance = \OCP\Server::get($fqcn);
			$settings = $instance->getSettings();
			if ($fqcn === $name || $settings->id === $name) {
				$instance->setAppConfig(\OCP\Server::get(\OCP\IAppConfig::class));
				$this->instances[$this->resolveInstanceCacheKey($name, $instance)] = $instance;
				$this->instancesByFqcn[$fqcn] = $instance;
				return $instance;
			}
		}
		throw new InvalidProviderException("Provider <$name> does not exist");
	}
}
