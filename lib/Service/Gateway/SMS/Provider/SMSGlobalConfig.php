<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class SMSGlobalConfig extends AGatewayConfig {
	protected const expected = [
		'smsglobal_url',
		'smsglobal_user',
		'smsglobal_password',
	];

	public function getUrl(): string {
		return $this->getOrFail('smsglobal_url');
	}

	public function setUrl(string $url): void {
		$this->config->setValueString(Application::APP_ID, 'smsglobal_url', $url);
	}

	public function getUser(): string {
		return $this->getOrFail('smsglobal_user');
	}

	public function setUser(string $user): void {
		$this->config->setValueString(Application::APP_ID, 'smsglobal_user', $user);
	}

	public function getPassword(): string {
		return $this->getOrFail('smsglobal_password');
	}

	public function setPassword(string $password): void {
		$this->config->setValueString(Application::APP_ID, 'smsglobal_password', $password);
	}
}
