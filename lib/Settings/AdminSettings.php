<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Settings;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\IDelegatedSettings;

class AdminSettings implements IDelegatedSettings {
	#[\Override]
	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'admin_settings');
	}

	#[\Override]
	public function getSection(): string {
		return 'security';
	}

	#[\Override]
	public function getPriority(): int {
		return 70;
	}

	#[\Override]
	public function getName(): ?string {
		return 'Two-Factor Gateway';
	}

	#[\Override]
	public function getAuthorizedAppConfig(): array {
		return [
			Application::APP_ID => ['/instances:.*/', '/.*_.*/'],
		];
	}
}
