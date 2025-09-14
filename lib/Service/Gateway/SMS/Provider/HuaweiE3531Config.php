<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Martin Keßler <martin@moegger.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class HuaweiE3531Config extends AGatewayConfig {
	protected const expected = [
		'huawei_e3531_api',
	];

	public function getUrl(): string {
		return $this->getOrFail('huawei_e3531_api');
	}

	public function setUrl(string $url): void {
		$this->config->setValueString(Application::APP_ID, 'huawei_e3531_api', $url);
	}
}
