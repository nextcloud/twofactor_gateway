<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Ruben de Wit <ruben@iamit.nl>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class SpryngSMSConfig extends AGatewayConfig {
	protected const expected = [
		'spryng_apitoken'
	];

	public function getApiToken(): string {
		return $this->getOrFail('spryng_apitoken');
	}

	public function setApiToken(string $user): void {
		$this->config->getValueString(Application::APP_ID, 'spryng_apitoken', $user);
	}
}
