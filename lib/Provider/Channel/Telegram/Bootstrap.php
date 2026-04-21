<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram;

use OCA\TwoFactorGateway\Events\TelegramAuthenticationErrorEvent;
use OCA\TwoFactorGateway\Listener\NotificationListener;
use OCA\TwoFactorGateway\Provider\Gateway\IGatewayBootstrap;
use OCA\TwoFactorGateway\Service\TelegramClientSessionMonitorJobManager;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Server;

class Bootstrap implements IGatewayBootstrap {
	#[\Override]
	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(TelegramAuthenticationErrorEvent::class, NotificationListener::class);

		Server::get(TelegramClientSessionMonitorJobManager::class)->ensureReconcileJobRegisteredSafely();
	}
}
