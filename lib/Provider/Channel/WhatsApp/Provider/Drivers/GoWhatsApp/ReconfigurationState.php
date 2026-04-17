<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCP\IAppConfig;

final class ReconfigurationState {
	private const APPCONFIG_KEY_REQUIRES_RECONFIGURE = 'gowhatsapp_requires_reconfigure';

	public static function isRequired(IAppConfig $appConfig): bool {
		return $appConfig->getValueString(
			Application::APP_ID,
			self::APPCONFIG_KEY_REQUIRES_RECONFIGURE,
			'0',
		) === '1';
	}

	public static function markRequired(IAppConfig $appConfig): void {
		$appConfig->setValueString(
			Application::APP_ID,
			self::APPCONFIG_KEY_REQUIRES_RECONFIGURE,
			'1',
		);
	}

	public static function clear(IAppConfig $appConfig): void {
		$appConfig->setValueString(
			Application::APP_ID,
			self::APPCONFIG_KEY_REQUIRES_RECONFIGURE,
			'0',
		);
	}
}
