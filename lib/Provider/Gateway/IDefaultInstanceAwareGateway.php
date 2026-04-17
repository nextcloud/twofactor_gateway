<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Gateway;

/**
 * Optional hook for gateways that need provider-specific side effects whenever
 * an instance becomes the active/default one.
 */
interface IDefaultInstanceAwareGateway {
	public function onDefaultInstanceActivated(): void;
}
