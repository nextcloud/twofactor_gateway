<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\Notification;

use OCA\TwoFactorGateway\Notification\AdminNotificationFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;

class WhatsAppAdminNotificationFormatter implements AdminNotificationFormatter {
	#[\Override]
	public function supports(string $subject): bool {
		return $subject === 'whatsapp_auth_error' || $subject === 'whatsapp_session_warning';
	}

	#[\Override]
	public function parse(INotification $notification, IL10N $l, IURLGenerator $url): INotification {
		if ($notification->getSubject() === 'whatsapp_auth_error') {
			$notification
				->setParsedSubject($l->t('Two-Factor Gateway: WhatsApp authentication failed'))
				->setParsedMessage($l->t('Two-Factor Gateway cannot send WhatsApp verification codes until this is fixed. Reconfigure the GoWhatsApp gateway from the command line using: occ twofactorauth:gateway:configure gowhatsapp'))
				->setIcon($url->getAbsoluteURL($url->imagePath('core', 'actions/error.svg')))
				->setLink($url->linkToRouteAbsolute('settings.AdminSettings.index', ['section' => 'overview']));

			return $notification;
		}

		if ($notification->getSubject() === 'whatsapp_session_warning') {
			$params = $notification->getSubjectParameters();
			$reason = $params['reason'] ?? '';

			$notification
				->setParsedSubject($l->t('Two-Factor Gateway: WhatsApp session is unstable'))
				->setParsedMessage(
					$reason !== ''
						? $l->t('WhatsApp session warning: %s', [$reason])
						: $l->t('The WhatsApp session is unstable and may require re-authentication soon. Monitor the session and reconfigure the GoWhatsApp gateway if needed.')
				)
				->setIcon($url->getAbsoluteURL($url->imagePath('core', 'actions/alert-outline.svg')))
				->setLink($url->linkToRouteAbsolute('settings.AdminSettings.index', ['section' => 'overview']));

			return $notification;
		}

		throw new UnknownNotificationException();
	}
}