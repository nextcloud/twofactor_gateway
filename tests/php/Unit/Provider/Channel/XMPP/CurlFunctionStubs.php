<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\XMPP;

if (!defined('CURLOPT_URL')) {
	define('CURLOPT_URL', 10002);
}
if (!defined('CURLOPT_RETURNTRANSFER')) {
	define('CURLOPT_RETURNTRANSFER', 19913);
}
if (!defined('CURLOPT_POST')) {
	define('CURLOPT_POST', 47);
}
if (!defined('CURLOPT_POSTFIELDS')) {
	define('CURLOPT_POSTFIELDS', 10015);
}
if (!defined('CURLOPT_USERPWD')) {
	define('CURLOPT_USERPWD', 10005);
}
if (!defined('CURLOPT_HTTPHEADER')) {
	define('CURLOPT_HTTPHEADER', 10023);
}

final class CurlFunctionStubs {
	/** @var list<array{option:int, value:mixed}> */
	public static array $setoptCalls = [];

	public static function reset(): void {
		self::$setoptCalls = [];
	}
}

function curl_init(): object {
	return new \stdClass();
}

function curl_setopt(object $handle, int $option, mixed $value): bool {
	CurlFunctionStubs::$setoptCalls[] = [
		'option' => $option,
		'value' => $value,
	];

	return true;
}

function curl_exec(object $handle): string {
	return '';
}

function curl_close(object $handle): void {
	// no-op for tests
}
