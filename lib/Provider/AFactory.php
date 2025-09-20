<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider;

abstract class AFactory {
	/** @var array<string,object> */
	protected array $instances = [];
	/** @var array<string> */
	protected array $fqcn = [];

	abstract protected function getPrefix(): string;
	abstract protected function getSuffix(): string;
	abstract protected function getBaseClass(): string;

	public function isValid(string $fqcn): bool {
		return true;
	}

	/** @return object */
	public function get(string $name): object {
		$needle = strtolower($name);
		if (isset($this->instances[$needle])) {
			return $this->instances[$needle];
		}

		foreach ($this->getFqcnList() as $fqcn) {
			$type = $this->typeFrom($fqcn);
			if ($type !== $needle) {
				continue;
			}
			$instance = \OCP\Server::get($fqcn);
			$this->instances[$needle] = $instance;
			return $instance;
		}

		throw new \InvalidArgumentException("Invalid type <$name>");
	}

	/** @return array<string> */
	public function getFqcnList(): array {
		if (!empty($this->fqcn)) {
			return $this->fqcn;
		}

		$loader = require __DIR__ . '/../../vendor/autoload.php';
		foreach ($loader->getClassMap() as $fqcn => $_) {
			$type = $this->typeFrom($fqcn);
			if ($type === null) {
				continue;
			}
			if (!is_subclass_of($fqcn, $this->getBaseClass(), true)) {
				continue;
			}
			if (!$this->isValid($fqcn)) {
				continue;
			}
			$this->fqcn[] = $fqcn;
		}
		return $this->fqcn;
	}

	protected function typeFrom(string $fqcn): ?string {
		$prefix = $this->getPrefix();
		if (strncmp($fqcn, $prefix, strlen($prefix)) !== 0) {
			return null;
		}
		$suffix = $this->getSuffix();
		if (!str_ends_with($fqcn, $suffix)) {
			return null;
		}
		$type = substr($fqcn, strlen($prefix));
		$type = substr($type, 0, -strlen('\\' . $suffix));
		if (strpos($type, '\\') !== false || $type === '') {
			return null;
		}
		return strtolower($type);
	}
}
