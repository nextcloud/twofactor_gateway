<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Notification;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Notification\TelegramAdminNotificationFormatter;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Notification\WhatsAppAdminNotificationFormatter;
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
		$subject = $notification->getSubject();

		foreach ($this->getFormatters() as $formatter) {
			if (!$formatter->supports($subject)) {
				continue;
			}

			$this->logger->debug('Preparing gateway admin notification for user ' . $notification->getUser(), [
				'subject' => $subject,
			]);

			return $formatter->parse($notification, $l, $this->url);
		}

		$this->logger->warning('Unknown notification subject: ' . $subject);
		throw new UnknownNotificationException();
	}

	/**
	 * @return list<AdminNotificationFormatter>
	 */
	private function getFormatters(): array {
		return [
			new WhatsAppAdminNotificationFormatter(),
			new TelegramAdminNotificationFormatter(),
		];
	}
}
