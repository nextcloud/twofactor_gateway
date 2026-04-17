<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCA\TwoFactorGateway\Provider\Gateway\IConfigurationChangeAwareGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;

class GatewayConfigurationSyncService {
	public function syncAfterConfigurationChange(IGateway $gateway): void {
		if (!($gateway instanceof IConfigurationChangeAwareGateway)) {
			return;
		}

		try {
			$gateway->syncAfterConfigurationChange();
		} catch (\Throwable) {
			// Sync failures must not break gateway configuration workflows.
		}
	}
}
