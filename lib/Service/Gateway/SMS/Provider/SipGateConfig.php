<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCP\IAppConfig;

class SipGateConfig implements IProviderConfig {
	private const expected = [
		'sipgate_token_id',
		'sipgate_access_token',
		'sipgate_web_sms_extension',
	];

	public function __construct(
		private IAppConfig $config,
	) {
	}

	private function getOrFail(string $key): string {
		$val = $this->config->getValueString(Application::APP_ID, $key);
		if (empty($val)) {
			throw new ConfigurationException();
		}
		return $val;
	}

	public function getTokenId(): string {
		return $this->getOrFail('sipgate_token_id');
	}

	public function setTokenId(string $tokenId): void {
		$this->config->getValueString(Application::APP_ID, 'sipgate_token_id', $tokenId);
	}

	public function getAccessToken(): string {
		return $this->getOrFail('sipgate_access_token');
	}

	public function setAccessToken(string $accessToken): void {
		$this->config->getValueString(Application::APP_ID, 'sipgate_access_token', $accessToken);
	}

	public function getWebSmsExtension(): string {
		return $this->getOrFail('sipgate_web_sms_extension');
	}

	public function setWebSmsExtension(string $webSmsExtension): void {
		$this->config->getValueString(Application::APP_ID, 'sipgate_web_sms_extension', $webSmsExtension);
	}
	#[\Override]
	public function isComplete(): bool {
		$set = $this->config->getKeys(Application::APP_ID);
		return count(array_intersect($set, self::expected)) === count(self::expected);
	}

	#[\Override]
	public function remove() {
		foreach (self::expected as $key) {
			$this->config->deleteKey(Application::APP_ID, $key);
		}
	}
}
