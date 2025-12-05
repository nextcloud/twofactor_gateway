<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Settings\Admin;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\Settings\ISettings;
use OCP\Util;

class AdminSettings implements ISettings {
	public function __construct(
		private IAppConfig $appConfig,
		private IInitialState $initialState,
	) {
	}

	public function getForm(): TemplateResponse {
		Util::addScript('twofactor_gateway', 'whatsapp-settings-simple');

		return new TemplateResponse('twofactor_gateway', 'admin_whatsapp_settings');
	}

	public function getSection(): string {
		return 'twofactor_gateway';
	}

	public function getPriority(): int {
		return 10;
	}
}
