<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

class GatewayConfigurationSyncService {
	public function __construct(
		private GoWhatsAppSessionMonitorJobManager $goWhatsAppSessionMonitorJobManager,
	) {
	}

	public function syncAfterConfigurationChange(): void {
		try {
			$this->goWhatsAppSessionMonitorJobManager->sync();
		} catch (\Throwable) {
			// Sync failures must not break gateway configuration workflows.
		}
	}
}
