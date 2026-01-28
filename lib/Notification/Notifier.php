<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Notification;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;
use Override;
use Psr\Log\LoggerInterface;

class Notifier implements INotifier {
	public function __construct(
		private IFactory $factory,
		private IURLGenerator $url,
		private LoggerInterface $logger,
	) {
	}

	#[Override]
	public function getID(): string {
		return Application::APP_ID;
	}

	#[Override]
	public function getName(): string {
		return $this->factory->get(Application::APP_ID)->t('Two-Factor Gateway');
	}

	#[Override]
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}

		$l = $this->factory->get(Application::APP_ID, $languageCode);

		if ($notification->getSubject() === 'whatsapp_auth_error') {
			$this->logger->debug('Preparing WhatsApp auth error notification for user ' . $notification->getUser());
			return $this->parseWhatsAppAuthError($notification, $l);
		}

		$this->logger->warning('Unknown notification subject: ' . $notification->getSubject());
		throw new UnknownNotificationException();
	}

	private function parseWhatsAppAuthError(INotification $notification, \OCP\IL10N $l): INotification {
		$notification
			->setParsedSubject($l->t('Two-Factor Gateway: WhatsApp API authentication failed'))
			->setParsedMessage($l->t('The authentication with WhatsApp API has failed. The Two-Factor Gateway (WhatsApp) will not work until this is resolved. Please reconfigure the WhatsApp gateway using the command line tool.'))
			->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/error.svg')))
			->setLink($this->url->linkToRouteAbsolute('settings.AdminSettings.index', ['section' => 'overview']));

		return $notification;
	}
}
