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
	public function __construct(int $code = 0, ?Throwable $previous = null) {
		parent::__construct('Invalid gateway/provider configuration set', $code, $previous);
	}
}
