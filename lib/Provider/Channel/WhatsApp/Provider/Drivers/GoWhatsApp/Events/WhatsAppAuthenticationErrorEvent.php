<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\Events;

use OCA\TwoFactorGateway\Events\AdminNotifiableEvent;
use OCP\EventDispatcher\Event;

class WhatsAppAuthenticationErrorEvent extends Event implements AdminNotifiableEvent {
	#[\Override]
	public function getNotificationSubject(): string {
		return 'whatsapp_auth_error';
	}

	#[\Override]
	public function getNotificationObjectType(): string {
		return 'whatsapp_error';
	}

	#[\Override]
	public function getNotificationObjectId(): string {
		return 'authentication';
	}

	/**
	 * @return array<string, string>
	 */
	#[\Override]
	public function getNotificationParameters(): array {
		return [];
	}
}
