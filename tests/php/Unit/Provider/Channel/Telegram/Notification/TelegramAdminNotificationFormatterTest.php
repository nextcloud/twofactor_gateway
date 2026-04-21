<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\Telegram\Notification;

use OCA\TwoFactorGateway\Provider\Channel\Telegram\Notification\TelegramAdminNotificationFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TelegramAdminNotificationFormatterTest extends TestCase {
	public function testSupportsTelegramAuthErrorOnly(): void {
		$formatter = new TelegramAdminNotificationFormatter();

		$this->assertTrue($formatter->supports('telegram_auth_error'));
		$this->assertFalse($formatter->supports('whatsapp_auth_error'));
	}

	public function testParseBuildsTelegramAdminNotification(): void {
		$formatter = new TelegramAdminNotificationFormatter();
		$l10n = $this->createTranslator();
		$url = $this->createMock(IURLGenerator::class);
		$url->method('imagePath')->with('core', 'actions/error.svg')->willReturn('/core/actions/error.svg');
		$url->method('getAbsoluteURL')->with('/core/actions/error.svg')->willReturn('https://example.test/core/actions/error.svg');
		$url->method('linkToRouteAbsolute')->with('settings.AdminSettings.index', ['section' => 'overview'])->willReturn('https://example.test/settings/admin/overview');

		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('setParsedSubject')
			->with('Two-Factor Gateway: Telegram Client session disconnected')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setParsedMessage')
			->with('Two-Factor Gateway cannot send Telegram verification codes through Telegram Client until login is restored. Open the Two-Factor Gateway admin settings and run Telegram Client interactive setup again.')
			->willReturnSelf();
		$notification->expects($this->once())->method('setIcon')->willReturnSelf();
		$notification->expects($this->once())->method('setLink')->willReturnSelf();

		$result = $formatter->parse($notification, $l10n, $url);
		$this->assertSame($notification, $result);
	}

	private function createTranslator(): IL10N&MockObject {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

		return $l10n;
	}
}
