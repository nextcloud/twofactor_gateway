<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Notification;

class AdminNotificationFormatterRegistry {
	/** @var list<AdminNotificationFormatter> */
	private array $formatters = [];

	public function register(AdminNotificationFormatter $formatter): void {
		$this->formatters[] = $formatter;
	}

	/**
	 * @return list<AdminNotificationFormatter>
	 */
	public function getFormatters(): array {
		return $this->formatters;
	}
}
