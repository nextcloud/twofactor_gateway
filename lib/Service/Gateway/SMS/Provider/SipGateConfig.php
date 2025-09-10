<?php

declare(strict_types=1);

/**
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
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
