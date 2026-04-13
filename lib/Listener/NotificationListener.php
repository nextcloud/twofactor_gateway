<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Listener;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Events\WhatsAppAuthenticationErrorEvent;
use OCA\TwoFactorGateway\Events\WhatsAppSessionWarningEvent;
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
		if ($event instanceof WhatsAppAuthenticationErrorEvent) {
			$this->notifyAdmins('whatsapp_auth_error');
			return;
		}

		if ($event instanceof WhatsAppSessionWarningEvent) {
			$this->notifyAdmins('whatsapp_session_warning', [
				'risk_score' => (string)$event->getRiskScore(),
				'reason' => $event->getReason(),
			]);
			return;
		}
	}

	private function notifyAdmins(string $subject, array $parameters = []): void {
		try {
			$adminGroup = $this->groupManager->get('admin');
			if ($adminGroup === null) {
				$this->logger->error('Admin group not found');
				return;
			}

			$admins = $adminGroup->getUsers();
			$this->logger->info('Found ' . count($admins) . ' admins to notify');

			$objectId = $subject === 'whatsapp_auth_error' ? 'authentication' : 'session_health';

			foreach ($admins as $user) {
				try {
					$notification = $this->notificationManager->createNotification();
					$notification
						->setApp(Application::APP_ID)
						->setDateTime($this->timeFactory->getDateTime())
						->setObject('whatsapp_error', $objectId)
						->setSubject($subject, $parameters)
						->setUser($user->getUID());

					$this->logger->info('About to notify user: ' . $user->getUID());
					$this->notificationManager->notify($notification);
					$this->logger->info('WhatsApp notification sent to ' . $user->getUID());
				} catch (\Exception $e) {
					$this->logger->error('Failed to notify user ' . $user->getUID() . ': ' . $e->getMessage(), [
						'exception' => $e,
					]);
				}
			}
		} catch (\Exception $e) {
			$this->logger->error('Error notifying admins about WhatsApp event: ' . $e->getMessage(), [
				'exception' => $e,
			]);
		}
	}
}
