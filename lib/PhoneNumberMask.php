<?php

/**
 * SPDX-FileCopyrightText: 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway;

class PhoneNumberMask {

	/**
	 * convert 123456789 to ******789
	 */
	public static function maskNumber(string $number): string {
		$number = trim($number);
		$length = strlen($number);
		if ($length <= 3) {
			return str_repeat('*', $length);
		}

		$start = $length - 3;

		return str_repeat('*', $start) . substr($number, $start);
	}

	public static function maskIdentifier(string $identifier): string {
		$identifier = trim($identifier);
		if ($identifier === '') {
			return '';
		}

		if (preg_match('/^\+?[0-9][0-9\s().-]*$/', $identifier) === 1) {
			$digits = preg_replace('/\D+/', '', $identifier);
			if (is_string($digits) && $digits !== '') {
				$maskedDigits = self::maskNumber($digits);

				return str_starts_with($identifier, '+')
					? '+' . $maskedDigits
					: $maskedDigits;
			}
		}

		return self::maskTail($identifier);
	}

	public static function redactMessage(string $message): string {
		return '[redacted]';
	}

	private static function maskTail(string $value): string {
		$length = mb_strlen($value);
		if ($length <= 3) {
			return str_repeat('*', $length);
		}

		return str_repeat('*', $length - 3) . mb_substr($value, -3);
	}
}
