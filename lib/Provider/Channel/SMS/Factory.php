<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS;

use OCA\TwoFactorGateway\Exception\InvalidProviderException;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\IProvider;
use OCP\IAppConfig;
use OCP\Server;

class Factory {
	/** @var array<string,IProvider> */
	private array $instances = [];
	/** @var array<string> */
	private array $fqcn = [];

	public function getProvider(string $id): IProvider {
		if (isset($this->instances[$id])) {
			return $this->instances[$id];
		}
		foreach ($this->getFqcnList() as $provider) {
			if ($provider::getProviderId() === $id) {
				$this->instances[$id] = Server::get($provider);
				$this->instances[$id]->setAppConfig(Server::get(IAppConfig::class));
				return $this->instances[$id];
			}
		}
		throw new InvalidProviderException("Provider <$id> does not exist");
	}

	/**
	 * @return array<string> List of provider class names
	 */
	public function getFqcnList(): array {
		if (!empty($this->fqcn)) {
			return $this->fqcn;
		}

		$loader = require __DIR__ . '/../../../../vendor/autoload.php';
		$classMap = $loader->getClassMap();

		$prefix = 'OCA\\TwoFactorGateway\\Provider\\Channel\\SMS\\Provider\\';

		foreach (array_keys($classMap) as $fqcn) {
			if (strncmp($fqcn, $prefix, strlen($prefix)) !== 0) {
				continue;
			}

			if (!class_exists($fqcn)) {
				continue;
			}

			if (!is_subclass_of($fqcn, IProvider::class)) {
				continue;
			}

			$rc = new \ReflectionClass($fqcn);
			if ($rc->isAbstract() || $rc->isInterface() || $rc->isTrait()) {
				continue;
			}

			if (!defined("$fqcn::SCHEMA") || !is_array($fqcn::SCHEMA)) {
				continue;
			}

			$this->fqcn[] = $fqcn;
		}

		return $this->fqcn;
	}
}
