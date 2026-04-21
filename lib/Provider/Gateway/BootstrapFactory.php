<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Gateway;

use OCA\TwoFactorGateway\Provider\AFactory;

/**
 * Auto-discovers all classes implementing IGatewayBootstrap from the composer
 * classmap. Convention: one Bootstrap class per gateway channel folder,
 * e.g. Provider/Channel/MyGateway/Bootstrap.php.
 *
 * @extends AFactory<IGatewayBootstrap>
 */
class BootstrapFactory extends AFactory {
	#[\Override]
	protected function getPrefix(): string {
		return 'OCA\\TwoFactorGateway\\Provider\\Channel\\';
	}

	#[\Override]
	protected function getSuffix(): string {
		return 'Bootstrap';
	}

	#[\Override]
	protected function getBaseClass(): string {
		return IGatewayBootstrap::class;
	}

	/** @return list<IGatewayBootstrap> */
	public function getInstances(): array {
		$instances = [];
		foreach ($this->getFqcnList() as $fqcn) {
			$instances[] = \OCP\Server::get($fqcn);
		}

		return $instances;
	}
}
