<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider;

enum FieldSensitivity: string {
	case NORMAL = 'normal';
	case SECRET = 'secret';

	public static function fromNullable(string|self|null $sensitivity): self {
		if ($sensitivity instanceof self) {
			return $sensitivity;
		}

		if ($sensitivity === null || $sensitivity === '') {
			return self::NORMAL;
		}

		return self::tryFrom($sensitivity) ?? self::NORMAL;
	}
}
