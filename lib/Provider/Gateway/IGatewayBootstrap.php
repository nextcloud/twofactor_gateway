<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Gateway;

use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * Optional interface for gateways that need to register Nextcloud app-level
 * services (event listeners, background jobs, etc.) during app bootstrap.
 *
 * Implement this interface in a `Bootstrap` class inside the gateway's channel
 * folder (e.g. `Provider/Channel/MyGateway/Bootstrap.php`).  The class is
 * auto-discovered via the composer classmap — no changes to Application.php
 * are required when adding new gateways.
 */
interface IGatewayBootstrap {
	public function register(IRegistrationContext $context): void;
}
