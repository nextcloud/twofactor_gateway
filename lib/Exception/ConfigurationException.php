<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Exception;

use Exception;
use Throwable;

class ConfigurationException extends Exception {
	public function __construct(?string $message = null, ?Throwable $previous = null) {
		if (!$message) {
			$message = 'Invalid gateway/provider configuration set';
		}
		parent::__construct(message: $message, previous: $previous);
	}
}
