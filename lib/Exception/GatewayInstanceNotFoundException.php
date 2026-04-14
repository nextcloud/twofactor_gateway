<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Exception;

use RuntimeException;

class GatewayInstanceNotFoundException extends RuntimeException {
	public function __construct(string $gatewayId, string $instanceId) {
		parent::__construct("Gateway instance '$instanceId' not found for gateway '$gatewayId'");
	}
}
