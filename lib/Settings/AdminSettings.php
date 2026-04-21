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
	/**
	 * Restrict delegated AppConfig access to instance registries and per-instance fields.
	 *
	 * The admin UI manages gateway instances through dedicated OCS endpoints. It only
	 * needs access to the instance registry namespace and the per-instance field keys
	 * used by the multi-instance storage model.
	 *
	 * Legacy single-instance keys such as `telegram_provider_name` or operational keys
	 * such as `gowhatsapp_webhook_secret` remain server-side only and must not be
	 * exposed through delegated settings authorization.
	 *
	 * @return list<string>
	 */
	private function getAuthorizedConfigPatterns(): array {
		return [
			'/^instances:[^:]+$/',
			'/^[^:]+:[^:]+:[^:]+$/',
		];
	}

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
			Application::APP_ID => $this->getAuthorizedConfigPatterns(),
		];
	}
}
