<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\WhatsApp\Notification;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Notification\WhatsAppAdminNotificationFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WhatsAppAdminNotificationFormatterTest extends TestCase {
	public function testSupportsWhitelistedSubjects(): void {
		$formatter = new WhatsAppAdminNotificationFormatter();

		$this->assertTrue($formatter->supports('whatsapp_auth_error'));
		$this->assertTrue($formatter->supports('whatsapp_session_warning'));
		$this->assertFalse($formatter->supports('telegram_auth_error'));
	}

	public function testParseWarningUsesReasonWhenProvided(): void {
		$formatter = new WhatsAppAdminNotificationFormatter();
		$calls = [];
		$l10n = $this->createTranslator($calls);
		$url = $this->createUrlForAlertIcon();
		$notification = $this->createMock(INotification::class);

		$notification->method('getSubject')->willReturn('whatsapp_session_warning');
		$notification->method('getSubjectParameters')->willReturn(['reason' => 'risk score reached 40']);
		$notification->expects($this->once())
			->method('setParsedSubject')
			->with($this->isType('string'))
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setParsedMessage')
			->with($this->callback(static function (string $message): bool {
				return str_contains($message, 'risk score reached 40');
			}))
			->willReturnSelf();
		$notification->expects($this->once())->method('setIcon')->willReturnSelf();
		$notification->expects($this->once())->method('setLink')->willReturnSelf();

		$result = $formatter->parse($notification, $l10n, $url);
		$this->assertSame($notification, $result);
		$this->assertContains(['risk score reached 40'], $calls);
	}

	public function testParseWarningUsesFallbackMessageWithoutReason(): void {
		$formatter = new WhatsAppAdminNotificationFormatter();
		$calls = [];
		$l10n = $this->createTranslator($calls);
		$url = $this->createUrlForAlertIcon();
		$notification = $this->createMock(INotification::class);

		$notification->method('getSubject')->willReturn('whatsapp_session_warning');
		$notification->method('getSubjectParameters')->willReturn([]);
		$notification->expects($this->once())
			->method('setParsedMessage')
			->with($this->isType('string'))
			->willReturnSelf();
		$notification->method('setParsedSubject')->willReturnSelf();
		$notification->method('setIcon')->willReturnSelf();
		$notification->method('setLink')->willReturnSelf();

		$formatter->parse($notification, $l10n, $url);
		$this->assertNotContains(['risk score reached 40'], $calls);
	}

	public function testParseAuthErrorUsesErrorIconAndOverviewLink(): void {
		$formatter = new WhatsAppAdminNotificationFormatter();
		$l10n = $this->createTranslator();
		$url = $this->createMock(IURLGenerator::class);
		$url->expects($this->once())->method('imagePath')->with('core', 'actions/error.svg')->willReturn('/core/actions/error.svg');
		$url->expects($this->once())->method('getAbsoluteURL')->with('/core/actions/error.svg')->willReturn('https://example.test/core/actions/error.svg');
		$url->expects($this->once())->method('linkToRouteAbsolute')->with('settings.AdminSettings.index', ['section' => 'overview'])->willReturn('https://example.test/settings/admin/overview');

		$notification = $this->createMock(INotification::class);
		$notification->method('getSubject')->willReturn('whatsapp_auth_error');
		$notification->expects($this->once())->method('setParsedSubject')->with($this->isType('string'))->willReturnSelf();
		$notification->expects($this->once())->method('setParsedMessage')->with($this->isType('string'))->willReturnSelf();
		$notification->expects($this->once())->method('setIcon')->with('https://example.test/core/actions/error.svg')->willReturnSelf();
		$notification->expects($this->once())->method('setLink')->with('https://example.test/settings/admin/overview')->willReturnSelf();

		$result = $formatter->parse($notification, $l10n, $url);
		$this->assertSame($notification, $result);
	}

	public function testParseThrowsForUnsupportedSubject(): void {
		$formatter = new WhatsAppAdminNotificationFormatter();
		$l10n = $this->createTranslator();
		$url = $this->createMock(IURLGenerator::class);
		$notification = $this->createMock(INotification::class);
		$notification->method('getSubject')->willReturn('telegram_auth_error');

		$this->expectException(UnknownNotificationException::class);
		$formatter->parse($notification, $l10n, $url);
	}

	private function createTranslator(array &$parameterCalls = []): IL10N&MockObject {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $text, array $parameters = [], ?int $count = null) use (&$parameterCalls): string {
			$parameterCalls[] = $parameters;

			if ($parameters === []) {
				return $text;
			}

			return (string)vsprintf(str_replace('%s', '%s', $text), $parameters);
		});

		return $l10n;
	}

	private function createUrlForAlertIcon(): IURLGenerator&MockObject {
		$url = $this->createMock(IURLGenerator::class);
		$url->expects($this->once())->method('imagePath')->with('core', 'actions/alert-outline.svg')->willReturn('/core/actions/alert-outline.svg');
		$url->expects($this->once())->method('getAbsoluteURL')->with('/core/actions/alert-outline.svg')->willReturn('https://example.test/core/actions/alert-outline.svg');
		$url->expects($this->once())->method('linkToRouteAbsolute')->with('settings.AdminSettings.index', ['section' => 'overview'])->willReturn('https://example.test/settings/admin/overview');

		return $url;
	}
}
