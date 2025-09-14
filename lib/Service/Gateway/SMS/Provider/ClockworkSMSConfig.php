<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Mario Klug <mario.klug@sourcefactory.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class ClockworkSMSConfig extends AGatewayConfig {
	protected const expected = [
		'clockworksms_apitoken'
	];

	public function getApiToken(): string {
		return $this->getOrFail('clockworksms_apitoken');
	}

	public function setApiToken(string $user): void {
		$this->config->setValueString(Application::APP_ID, 'clockworksms_apitoken', $user);
	}
}
