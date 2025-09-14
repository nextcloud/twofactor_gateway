<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\Signal;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;
use OCP\IAppConfig;

class GatewayConfig extends AGatewayConfig {
	protected const expected = [
		'signal_url',
	];

	public function __construct(
		public IAppConfig $config,
	) {
	}

	public function getUrl(): string {
		return $this->getOrFail('signal_url');
	}

	public function setUrl(string $url): void {
		$this->config->getValueString(Application::APP_ID, 'signal_url', $url);
	}
}
