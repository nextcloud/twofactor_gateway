<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christian Schrötter <cs@fnx.li>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class ClickatellCentralConfig extends AGatewayConfig {
	protected const expected = [
		'clickatell_central_api',
		'clickatell_central_user',
		'clickatell_central_password',
	];

	public function getApi(): string {
		return $this->getOrFail('clickatell_central_api');
	}

	public function setApi(string $api): void {
		$this->config->setValueString(Application::APP_ID, 'clickatell_central_api', $api);
	}

	public function getUser(): string {
		return $this->getOrFail('clickatell_central_user');
	}

	public function setUser(string $user): void {
		$this->config->setValueString(Application::APP_ID, 'clickatell_central_user', $user);
	}

	public function getPassword(): string {
		return $this->getOrFail('clickatell_central_password');
	}

	public function setPassword(string $password): void {
		$this->config->setValueString(Application::APP_ID, 'clickatell_central_password', $password);
	}
}
