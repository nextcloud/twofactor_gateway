<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway;

use InvalidArgumentException;
use OCP\Server;

class Factory {
	private const PREFIX = 'OCA\\TwoFactorGateway\\Service\\Gateway\\';

	public function getGateway(string $name): IGateway {
		$needle = strtolower($name);

		foreach ($this->getClassMap() as $fqcn => $_) {
			$type = $this->typeFrom($fqcn);
			if ($type === null || $type !== $needle) {
				continue;
			}
			if (!is_subclass_of($fqcn, AGateway::class, true)) {
				continue;
			}
			/** @var IGateway */
			return Server::get($fqcn);
		}
		throw new InvalidArgumentException("Invalid gateway $name");
	}

	private function getClassMap(): array {
		$loader = require __DIR__ . '/../../../vendor/autoload.php';
		return $loader->getClassMap();
	}

	private function typeFrom(string $fqcn): ?string {
		$p = self::PREFIX;
		if (strncmp($fqcn, $p, strlen($p)) !== 0) {
			return null;
		}

		$rest = substr($fqcn, strlen($p));
		$sep = strpos($rest, '\\');
		if ($sep === false || substr($rest, $sep + 1) !== 'Gateway') {
			return null;
		}

		$type = substr($rest, 0, $sep);
		return $type !== '' ? strtolower($type) : null;
	}
}
