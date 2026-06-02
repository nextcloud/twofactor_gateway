<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS;

use OCA\TwoFactorGateway\Provider\Channel\AbstractCatalogFactory;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\AProvider;

/** @extends AbstractCatalogFactory<AProvider> */
class Factory extends AbstractCatalogFactory {
	#[\Override]
	protected function getPrefix(): string {
		return 'OCA\\TwoFactorGateway\\Provider\\Channel\\SMS\\Provider\\Drivers\\';
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
	protected function resolveInstanceCacheKey(string $name, object $instance): string {
		$id = $instance->getSettings()->id;
		return $id !== null && $id !== '' ? $id : $name;
	}
}
