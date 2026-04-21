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
		$calls = [];
		$l10n = $this->createTranslator($calls);
		$url = $this->createMock(IURLGenerator::class);
		$url->expects($this->once())->method('imagePath')->with('core', 'actions/error.svg')->willReturn('/core/actions/error.svg');
		$url->expects($this->once())->method('getAbsoluteURL')->with('/core/actions/error.svg')->willReturn('https://example.test/core/actions/error.svg');
		$url->expects($this->once())->method('linkToRouteAbsolute')->with('settings.AdminSettings.index', ['section' => 'overview'])->willReturn('https://example.test/settings/admin/overview');

		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('setParsedSubject')
			->with($this->isType('string'))
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setParsedMessage')
			->with($this->isType('string'))
			->willReturnSelf();
		$notification->expects($this->once())->method('setIcon')->with('https://example.test/core/actions/error.svg')->willReturnSelf();
		$notification->expects($this->once())->method('setLink')->with('https://example.test/settings/admin/overview')->willReturnSelf();

		$result = $formatter->parse($notification, $l10n, $url);
		$this->assertSame($notification, $result);
		$this->assertSame([[], []], $calls);
	}

	private function createTranslator(array &$parameterCalls = []): IL10N&MockObject {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $text, array $parameters = [], ?int $count = null) use (&$parameterCalls): string {
			$parameterCalls[] = $parameters;
			return $text;
		});

		return $l10n;
	}
}
