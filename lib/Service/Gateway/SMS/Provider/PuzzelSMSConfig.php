<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Kim Syversen <kim.syversen@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class PuzzelSMSConfig extends AGatewayConfig {
	protected const expected = [
		'puzzel_url',
		'puzzel_user',
		'puzzel_password',
		'puzzel_serviceid',
	];

	public function getUrl(): string {
		return $this->getOrFail('puzzel_url');
	}

	public function setUrl(string $url): void {
		$this->config->setValueString(Application::APP_ID, 'puzzel_url', $url);
	}

	public function getUser(): string {
		return $this->getOrFail('puzzel_user');
	}

	public function setUser(string $user): void {
		$this->config->setValueString(Application::APP_ID, 'puzzel_user', $user);
	}

	public function getPassword(): string {
		return $this->getOrFail('puzzel_password');
	}

	public function setPassword(string $password): void {
		$this->config->setValueString(Application::APP_ID, 'puzzel_password', $password);
	}

	public function getServiceId(): string {
		return $this->getOrFail('puzzel_serviceid');
	}

	public function setServiceId(string $serviceid): void {
		$this->config->setValueString(Application::APP_ID, 'puzzel_serviceid', $serviceid);
	}
}
