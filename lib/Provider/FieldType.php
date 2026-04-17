<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider;

enum FieldType: string {
	case TEXT = 'text';
	case SECRET = 'secret';
	case BOOLEAN = 'boolean';
	case INTEGER = 'integer';

	public static function fromNullable(string|self|null $type): ?self {
		if ($type instanceof self) {
			return $type;
		}

		if ($type === null || $type === '') {
			return null;
		}

		return self::tryFrom($type) ?? self::TEXT;
	}
}
