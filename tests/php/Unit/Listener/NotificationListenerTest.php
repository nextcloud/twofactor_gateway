<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Listener;

use OCA\TwoFactorGateway\Events\WhatsAppAuthenticationErrorEvent;
use OCA\TwoFactorGateway\Listener\NotificationListener;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotificationListenerTest extends TestCase {
	private IManager&MockObject $notificationManager;
	private IGroupManager&MockObject $groupManager;
	private ITimeFactory&MockObject $timeFactory;
	private LoggerInterface&MockObject $logger;
	private NotificationListener $listener;

	protected function setUp(): void {
		parent::setUp();
		$this->notificationManager = $this->createMock(IManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new NotificationListener(
			$this->notificationManager,
			$this->groupManager,
			$this->timeFactory,
			$this->logger,
		);
	}

	public function testHandleSkipsDuplicateAuthErrorNotificationForAdmin(): void {
		$admin = $this->createMock(IUser::class);
		$admin->method('getUID')->willReturn('admin');
		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn([$admin]);
		$this->groupManager->method('get')->with('admin')->willReturn($group);

		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('getCount')
			->with($notification)
			->willReturn(1);
		$this->notificationManager->expects($this->never())
			->method('notify');

		$this->listener->handle(new WhatsAppAuthenticationErrorEvent());
	}

	public function testHandleSendsAuthErrorNotificationWhenNoActiveOneExists(): void {
		$this->timeFactory->method('getDateTime')->willReturn(new \DateTime());

		$admin = $this->createMock(IUser::class);
		$admin->method('getUID')->willReturn('admin');
		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn([$admin]);
		$this->groupManager->method('get')->with('admin')->willReturn($group);

		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->expects($this->once())
			->method('setDateTime')
			->willReturnSelf();

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('getCount')
			->with($notification)
			->willReturn(0);
		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->listener->handle(new WhatsAppAuthenticationErrorEvent());
	}

	public function testHandleIgnoresUnknownEvent(): void {
		$this->notificationManager->expects($this->never())->method('createNotification');
		$this->listener->handle(new class extends Event {
		});
	}
}
