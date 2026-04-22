<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Notification;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Notification\INotification;

interface AdminNotificationFormatter {
	public function supports(string $subject): bool;

	public function parse(INotification $notification, IL10N $l, IURLGenerator $url): INotification;
}
