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
		$l10n = $this->createTranslator();
		$url = $this->createUrlForAlertIcon();
		$notification = $this->createMock(INotification::class);

		$notification->method('getSubject')->willReturn('whatsapp_session_warning');
		$notification->method('getSubjectParameters')->willReturn(['reason' => 'risk score reached 40']);
		$notification->expects($this->once())
			->method('setParsedSubject')
			->with('Two-Factor Gateway: WhatsApp session is unstable')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setParsedMessage')
			->with('WhatsApp session warning: risk score reached 40')
			->willReturnSelf();
		$notification->expects($this->once())->method('setIcon')->willReturnSelf();
		$notification->expects($this->once())->method('setLink')->willReturnSelf();

		$result = $formatter->parse($notification, $l10n, $url);
		$this->assertSame($notification, $result);
	}

	public function testParseWarningUsesFallbackMessageWithoutReason(): void {
		$formatter = new WhatsAppAdminNotificationFormatter();
		$l10n = $this->createTranslator();
		$url = $this->createUrlForAlertIcon();
		$notification = $this->createMock(INotification::class);

		$notification->method('getSubject')->willReturn('whatsapp_session_warning');
		$notification->method('getSubjectParameters')->willReturn([]);
		$notification->expects($this->once())
			->method('setParsedMessage')
			->with('The WhatsApp session is unstable and may require re-authentication soon. Monitor the session and reconfigure the GoWhatsApp gateway if needed.')
			->willReturnSelf();
		$notification->method('setParsedSubject')->willReturnSelf();
		$notification->method('setIcon')->willReturnSelf();
		$notification->method('setLink')->willReturnSelf();

		$formatter->parse($notification, $l10n, $url);
	}

	public function testParseThrowsForUnsupportedSubject(): void {
		$formatter = new WhatsAppAdminNotificationFormatter();
		$l10n = $this->createTranslator();
		$url = $this->createUrlForAlertIcon();
		$notification = $this->createMock(INotification::class);
		$notification->method('getSubject')->willReturn('telegram_auth_error');

		$this->expectException(UnknownNotificationException::class);
		$formatter->parse($notification, $l10n, $url);
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

	private function createUrlForAlertIcon(): IURLGenerator&MockObject {
		$url = $this->createMock(IURLGenerator::class);
		$url->method('imagePath')->with('core', 'actions/alert-outline.svg')->willReturn('/core/actions/alert-outline.svg');
		$url->method('getAbsoluteURL')->with('/core/actions/alert-outline.svg')->willReturn('https://example.test/core/actions/alert-outline.svg');
		$url->method('linkToRouteAbsolute')->with('settings.AdminSettings.index', ['section' => 'overview'])->willReturn('https://example.test/settings/admin/overview');

		return $url;
	}
}
