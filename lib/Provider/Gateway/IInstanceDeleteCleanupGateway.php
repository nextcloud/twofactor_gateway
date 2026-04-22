<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Gateway;

/**
 * Optional hook for gateways that need to clean remote state before an
 * instance configuration is removed locally.
 */
interface IInstanceDeleteCleanupGateway {
	/**
	 * @param array<string, string> $instanceConfig
	 */
	public function cleanupDeletedInstance(array $instanceConfig): void;
}
