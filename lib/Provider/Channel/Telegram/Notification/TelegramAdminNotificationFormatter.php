<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Notification;

use OCA\TwoFactorGateway\Notification\AdminNotificationFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Notification\INotification;

class TelegramAdminNotificationFormatter implements AdminNotificationFormatter {
	#[\Override]
	public function supports(string $subject): bool {
		return $subject === 'telegram_auth_error';
	}

	#[\Override]
	public function parse(INotification $notification, IL10N $l, IURLGenerator $url): INotification {
		$notification
			->setParsedSubject($l->t('Two-Factor Gateway: Telegram Client session disconnected'))
			->setParsedMessage($l->t('Two-Factor Gateway cannot send Telegram verification codes through Telegram Client until login is restored. Open the Two-Factor Gateway admin settings and run Telegram Client interactive setup again.'))
			->setIcon($url->getAbsoluteURL($url->imagePath('core', 'actions/error.svg')))
			->setLink($url->linkToRouteAbsolute('settings.AdminSettings.index', ['section' => 'overview']));

		return $notification;
	}
}
