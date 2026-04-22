<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Listener;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Events\AdminNotifiableEvent;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class NotificationListener implements IEventListener {
	public function __construct(
		private IManager $notificationManager,
		private IGroupManager $groupManager,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof AdminNotifiableEvent) {
			$this->notifyAdmins(
				$event->getNotificationSubject(),
				$event->getNotificationObjectType(),
				$event->getNotificationObjectId(),
				$event->getNotificationParameters(),
			);
		}
	}

	/**
	 * @param array<string, string> $parameters
	 */
	private function notifyAdmins(string $subject, string $objectType, string $objectId, array $parameters = []): void {
		try {
			$adminGroup = $this->groupManager->get('admin');
			if ($adminGroup === null) {
				$this->logger->error('Admin group not found');
				return;
			}

			$admins = $adminGroup->getUsers();
			$this->logger->info('Found ' . count($admins) . ' admins to notify');

			foreach ($admins as $user) {
				try {
					$notification = $this->notificationManager->createNotification();
					$notification
						->setApp(Application::APP_ID)
						->setObject($objectType, $objectId)
						->setSubject($subject, $parameters)
						->setUser($user->getUID());

					if ($this->notificationManager->getCount($notification) > 0) {
						$this->logger->info('Skipping duplicate gateway notification for ' . $user->getUID(), [
							'subject' => $subject,
						]);
						continue;
					}

					$notification->setDateTime($this->timeFactory->getDateTime());
					$this->logger->info('About to notify user: ' . $user->getUID());
					$this->notificationManager->notify($notification);
					$this->logger->info('Gateway notification sent to ' . $user->getUID(), [
						'subject' => $subject,
					]);
				} catch (\Exception $e) {
					$this->logger->error('Failed to notify user ' . $user->getUID() . ': ' . $e->getMessage(), [
						'subject' => $subject,
						'exception' => $e,
					]);
				}
			}
		} catch (\Exception $e) {
			$this->logger->error('Error notifying admins about gateway event: ' . $e->getMessage(), [
				'subject' => $subject,
				'exception' => $e,
			]);
		}
	}
}
