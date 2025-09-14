<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class SipGateConfig extends AGatewayConfig {
	protected const expected = [
		'sipgate_token_id',
		'sipgate_access_token',
		'sipgate_web_sms_extension',
	];

	public function getTokenId(): string {
		return $this->getOrFail('sipgate_token_id');
	}

	public function setTokenId(string $tokenId): void {
		$this->config->setValueString(Application::APP_ID, 'sipgate_token_id', $tokenId);
	}

	public function getAccessToken(): string {
		return $this->getOrFail('sipgate_access_token');
	}

	public function setAccessToken(string $accessToken): void {
		$this->config->setValueString(Application::APP_ID, 'sipgate_access_token', $accessToken);
	}

	public function getWebSmsExtension(): string {
		return $this->getOrFail('sipgate_web_sms_extension');
	}

	public function setWebSmsExtension(string $webSmsExtension): void {
		$this->config->setValueString(Application::APP_ID, 'sipgate_web_sms_extension', $webSmsExtension);
	}
}
