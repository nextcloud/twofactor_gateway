<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Gateway;

use InvalidArgumentException;
use OCP\Server;

class Factory {
	/** @var array<string,IGateway> */
	private array $instances = [];
	/** @var array<string> */
	private array $fqcn = [];

	public function getGateway(string $name): IGateway {
		$needle = strtolower($name);
		if (isset($this->instances[$needle])) {
			return $this->instances[$needle];
		}

		foreach ($this->getFqcnList() as $fqcn) {
			$type = $this->typeFrom($fqcn);
			if ($type !== $needle) {
				continue;
			}
			/** @var IGateway */
			$instance = Server::get($fqcn);
			$this->instances[$needle] = $instance;
			return $instance;
		}
		throw new InvalidArgumentException("Invalid gateway $name");
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
			if (!is_subclass_of($fqcn, AGateway::class, true)) {
				continue;
			}
			$this->fqcn[] = $fqcn;
		}

		return $this->fqcn;
	}

	private function getClassMap(): array {
		$loader = require __DIR__ . '/../../../vendor/autoload.php';
		return $loader->getClassMap();
	}

	private function typeFrom(string $fqcn): ?string {
		$prefix = 'OCA\\TwoFactorGateway\\Provider\\Channel\\';
		if (strncmp($fqcn, $prefix, strlen($prefix)) !== 0) {
			return null;
		}

		if (!str_ends_with($fqcn, 'Gateway')) {
			return null;
		}

		$type = substr($fqcn, strlen($prefix));
		$type = substr($type, 0, -strlen('\Gateway'));

		if (strpos($type, '\\') !== false) {
			return null;
		}

		if ($type === '') {
			return null;
		}

		return strtolower($type);
	}
}
