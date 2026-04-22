<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Notification;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Notification\AdminNotificationFormatter;
use OCA\TwoFactorGateway\Notification\AdminNotificationFormatterRegistry;
use OCA\TwoFactorGateway\Notification\Notifier;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotifierTest extends TestCase {
	private IFactory&MockObject $factory;
	private IURLGenerator&MockObject $url;
	private LoggerInterface&MockObject $logger;
	private AdminNotificationFormatterRegistry $registry;
	private Notifier $notifier;

	protected function setUp(): void {
		parent::setUp();

		$this->factory = $this->createMock(IFactory::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->registry = new AdminNotificationFormatterRegistry();
		$this->notifier = new Notifier($this->factory, $this->url, $this->logger, $this->registry);
	}

	public function testPrepareThrowsForDifferentApp(): void {
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn('other-app');

		$this->expectException(UnknownNotificationException::class);
		$this->notifier->prepare($notification, 'en');
	}

	public function testPrepareThrowsForUnknownSubject(): void {
		$l10n = $this->createTranslator();
		$this->factory->method('get')->with(Application::APP_ID, 'en')->willReturn($l10n);

		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn(Application::APP_ID);
		$notification->method('getSubject')->willReturn('unknown_subject');
		$notification->method('getUser')->willReturn('admin');

		$this->logger->expects($this->once())->method('warning');

		$this->expectException(UnknownNotificationException::class);
		$this->notifier->prepare($notification, 'en');
	}

	public function testPrepareDelegatesToFirstSupportingFormatter(): void {
		$l10n = $this->createTranslator();
		$this->factory->method('get')->with(Application::APP_ID, 'en')->willReturn($l10n);

		$firstFormatter = $this->createMock(AdminNotificationFormatter::class);
		$firstFormatter->expects($this->once())
			->method('supports')
			->with('telegram_auth_error')
			->willReturn(false);
		$firstFormatter->expects($this->never())
			->method('parse');

		$secondFormatter = $this->createMock(AdminNotificationFormatter::class);
		$secondFormatter->expects($this->once())
			->method('supports')
			->with('telegram_auth_error')
			->willReturn(true);

		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn(Application::APP_ID);
		$notification->method('getSubject')->willReturn('telegram_auth_error');
		$notification->method('getUser')->willReturn('admin');

		$secondFormatter->expects($this->once())
			->method('parse')
			->with($notification, $l10n, $this->url)
			->willReturn($notification);

		$this->registry->register($firstFormatter);
		$this->registry->register($secondFormatter);

		$this->logger->expects($this->once())
			->method('debug');

		$result = $this->notifier->prepare($notification, 'en');
		$this->assertSame($notification, $result);
	}

	private function createTranslator(): IL10N&MockObject {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $text, array $parameters = []): string {
			if ($parameters === []) {
				return $text;
			}

			return (string)vsprintf(str_replace('%s', '%s', $text), $parameters);
		});

		return $l10n;
	}
}
