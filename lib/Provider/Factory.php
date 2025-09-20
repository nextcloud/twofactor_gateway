<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider;

use OCA\TwoFactorGateway\Exception\InvalidProviderException;
use OCP\Server;

class Factory {
	/** @var array<string,AProvider> */
	private array $instances = [];
	/** @var array<string> */
	private array $fqcn = [];

	/**
	 * @throws InvalidProviderException
	 */
	public function getProvider(string $name): AProvider {
		$needle = strtolower($name);
		if (isset($this->instances[$needle])) {
			return $this->instances[$needle];
		}

		foreach ($this->getFqcnList() as $fqcn) {
			$type = $this->typeFrom($fqcn);
			if ($type !== $needle) {
				continue;
			}
			/** @var AProvider */
			$instance = Server::get($fqcn);
			$this->instances[$needle] = $instance;
			return $instance;
		}
		throw new InvalidProviderException();
	}

	public function getFqcnList(): array {
		if (!empty($this->fqcn)) {
			return $this->fqcn;
		}

		foreach ($this->getClassMap() as $fqcn => $_) {
			$type = $this->typeFrom($fqcn);
			if ($type === null) {
				continue;
			}
			if (!is_subclass_of($fqcn, AProvider::class, true)) {
				continue;
			}
			$this->fqcn[] = $fqcn;
		}

		return $this->fqcn;
	}

	private function getClassMap(): array {
		$loader = require __DIR__ . '/../../vendor/autoload.php';
		return $loader->getClassMap();
	}

	private function typeFrom(string $fqcn): ?string {
		$prefix = 'OCA\\TwoFactorGateway\\Provider\\Channel\\';
		if (strncmp($fqcn, $prefix, strlen($prefix)) !== 0) {
			return null;
		}

		if (!str_ends_with($fqcn, 'Provider')) {
			return null;
		}

		$type = substr($fqcn, strlen($prefix));
		$type = substr($type, 0, -strlen('\Provider'));

		if (strpos($type, '\\') !== false) {
			return null;
		}

		if ($type === '') {
			return null;
		}

		return strtolower($type);
	}
}
