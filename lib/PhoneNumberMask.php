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
		$length = strlen($number);
		$start = $length - 3;

		return str_repeat('*', $start) . substr($number, $start);
	}
}
