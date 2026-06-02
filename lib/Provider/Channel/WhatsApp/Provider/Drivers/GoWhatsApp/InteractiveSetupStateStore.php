<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp;

use OCA\TwoFactorGateway\Provider\AbstractInteractiveSetupStateStore;

class InteractiveSetupStateStore extends AbstractInteractiveSetupStateStore {
	#[\Override]
	protected function getPrefix(): string {
		return 'gowhatsapp_setup_state:';
	}
}
