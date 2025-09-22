<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Gateway;

use OCA\TwoFactorGateway\Provider\AFactory;

class Factory extends AFactory {
	#[\Override]
	protected function getPrefix(): string {
		return 'OCA\\TwoFactorGateway\\Provider\\Channel\\';
	}

	#[\Override]
	protected function getSuffix(): string {
		return 'Gateway';
	}

	#[\Override]
	protected function getBaseClass(): string {
		return IGateway::class;
	}
}
