<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Gateway;

/**
 * Optional interface for gateways that can enrich a test result
 * with additional information about the configured account.
 *
 * Implementing this interface does not affect any other gateways.
 * The controller checks for the interface and calls it only when present.
 */
interface ITestResultEnricher {
	/**
	 * Return optional information about the sender account to surface in the UI.
	 *
	 * Called after a successful test message. The returned array may be empty
	 * (e.g. when the remote API is unreachable). Any non-empty values are
	 * forwarded to the frontend as-is.
	 *
	 * @param array<string, string> $instanceConfig Field values for the specific instance under test
	 * @return array<string, string>
	 */
	public function enrichTestResult(array $instanceConfig): array;
}
