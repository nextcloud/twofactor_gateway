<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Gateway;

/** Protocol-specific test messages for gateways that cannot send arbitrary text. */
interface ITestMessageProvider {
	/** @return array{message: string, extra: array<string, string>} */
	public function createTestMessage(): array;
}
