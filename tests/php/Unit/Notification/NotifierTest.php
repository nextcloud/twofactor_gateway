<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Notification;

use OCA\TwoFactorGateway\AppInfo\Application;
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
	private Notifier $notifier;

	protected function setUp(): void {
		parent::setUp();

		$this->factory = $this->createMock(IFactory::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->notifier = new Notifier($this->factory, $this->url, $this->logger);
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

	public function testPrepareParsesTelegramNotification(): void {
		$l10n = $this->createTranslator();
		$this->factory->method('get')->with(Application::APP_ID, 'en')->willReturn($l10n);

		$this->url->method('imagePath')->with('core', 'actions/error.svg')->willReturn('/core/actions/error.svg');
		$this->url->method('getAbsoluteURL')->with('/core/actions/error.svg')->willReturn('https://example.test/core/actions/error.svg');
		$this->url->method('linkToRouteAbsolute')->with('settings.AdminSettings.index', ['section' => 'overview'])->willReturn('https://example.test/settings/admin/overview');

		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn(Application::APP_ID);
		$notification->method('getSubject')->willReturn('telegram_auth_error');
		$notification->method('getUser')->willReturn('admin');
		$notification->expects($this->once())
			->method('setParsedSubject')
			->with('Two-Factor Gateway: Telegram Client session disconnected')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setParsedMessage')
			->with('Two-Factor Gateway cannot send Telegram verification codes through Telegram Client until login is restored. Open the Two-Factor Gateway admin settings and run Telegram Client interactive setup again.')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setIcon')
			->with('https://example.test/core/actions/error.svg')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setLink')
			->with('https://example.test/settings/admin/overview')
			->willReturnSelf();

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
