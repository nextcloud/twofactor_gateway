<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class PlaySMSConfig extends AGatewayConfig {
	protected const expected = [
		'playsms_url',
		'playsms_user',
		'playsms_password',
	];

	public function getUrl(): string {
		return $this->getOrFail('playsms_url');
	}

	public function setUrl(string $url): void {
		$this->config->getValueString(Application::APP_ID, 'playsms_url', $url);
	}

	public function getUser(): string {
		return $this->getOrFail('playsms_user');
	}

	public function setUser(string $user): void {
		$this->config->getValueString(Application::APP_ID, 'playsms_user', $user);
	}

	public function getPassword(): string {
		return $this->getOrFail('playsms_password');
	}

	public function setPassword(string $password): void {
		$this->config->getValueString(Application::APP_ID, 'playsms_password', $password);
	}
}
