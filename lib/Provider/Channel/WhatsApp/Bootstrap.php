<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp;

use OCA\TwoFactorGateway\Events\WhatsAppAuthenticationErrorEvent;
use OCA\TwoFactorGateway\Events\WhatsAppSessionWarningEvent;
use OCA\TwoFactorGateway\Listener\NotificationListener;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Service\GoWhatsAppSessionMonitorJobManager;
use OCA\TwoFactorGateway\Provider\Gateway\IGatewayBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Server;

class Bootstrap implements IGatewayBootstrap {
	#[\Override]
	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(WhatsAppAuthenticationErrorEvent::class, NotificationListener::class);
		$context->registerEventListener(WhatsAppSessionWarningEvent::class, NotificationListener::class);

		Server::get(GoWhatsAppSessionMonitorJobManager::class)->ensureReconcileJobRegisteredSafely();
	}
}
