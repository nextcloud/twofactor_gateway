<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram;

use OCA\TwoFactorGateway\Listener\NotificationListener;
use OCA\TwoFactorGateway\Notification\AdminNotificationFormatterRegistry;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Events\TelegramAuthenticationErrorEvent;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Notification\TelegramAdminNotificationFormatter;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Service\TelegramClientSessionMonitorJobManager;
use OCA\TwoFactorGateway\Provider\Gateway\IGatewayBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Server;

class Bootstrap implements IGatewayBootstrap {
	#[\Override]
	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(TelegramAuthenticationErrorEvent::class, NotificationListener::class);
		Server::get(AdminNotificationFormatterRegistry::class)->register(new TelegramAdminNotificationFormatter());

		Server::get(TelegramClientSessionMonitorJobManager::class)->ensureReconcileJobRegisteredSafely();
	}
}
