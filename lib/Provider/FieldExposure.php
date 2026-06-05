<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider;

enum FieldExposure: string {
	/** Default exposure when a field does not declare one explicitly. */
	case ADMIN = 'admin';
	case DELEGATED = 'delegated';
	case RUNTIME = 'runtime';
	case NEVER = 'never';

	public static function fromNullable(string|self|null $exposure): self {
		if ($exposure instanceof self) {
			return $exposure;
		}

		if ($exposure === null || $exposure === '') {
			return self::ADMIN;
		}

		return self::tryFrom($exposure) ?? self::ADMIN;
	}
}
