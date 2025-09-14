<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christian Schrötter <cs@fnx.li>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class ClickatellPortalConfig extends AGatewayConfig {
	public const expected = [
		'clickatell_portal_apikey',
	];

	public function getApiKey(): string {
		return $this->getOrFail('clickatell_portal_apikey');
	}

	public function setApiKey(string $apiKey): void {
		$this->config->setValueString(Application::APP_ID, 'clickatell_portal_apikey', $apiKey);
	}

	public function getFromNumber(): string {
		return $this->config->getValueString(Application::APP_ID, 'clickatell_portal_from');
	}

	public function setFromNumber(string $fromNumber): void {
		$this->config->setValueString(Application::APP_ID, 'clickatell_portal_from', $fromNumber);
	}

	public function deleteFromNumber(): void {
		$this->config->deleteKey(Application::APP_ID, 'clickatell_portal_from');
	}
}
