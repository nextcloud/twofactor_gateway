<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

enum GatewayViewScope: string {
	case ADMIN = 'admin';
	case DELEGATED = 'delegated';
	case RUNTIME = 'runtime';
}
