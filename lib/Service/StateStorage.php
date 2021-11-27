<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\TwoFactorGateway\Service;

use Exception;
use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Provider\SmsProvider;
use OCA\TwoFactorGateway\Provider\State;
use OCP\IConfig;
use OCP\IUser;

class StateStorage {

	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	private function buildConfigKey(string $gatewayName, string $key) {
		return "$gatewayName" . "_$key";
	}

	private function getUserValue(IUser $user, string $gatewayName, string $key, $default = '') {
		$gatewayKey = $this->buildConfigKey($gatewayName, $key);
		return $this->config->getUserValue($user->getUID(), Application::APP_ID, $gatewayKey, $default);
	}

	private function setUserValue(IUser $user, string $gatewayName, string $key, $value) {
		$gatewayKey = $this->buildConfigKey($gatewayName, $key);
		$this->config->setUserValue($user->getUID(), Application::APP_ID, $gatewayKey, $value);
	}

	private function deleteUserValue(IUser $user, string $gatewayName, string $key) {
		$gatewayKey = $this->buildConfigKey($gatewayName, $key);
		$this->config->deleteUserValue($user->getUID(), Application::APP_ID, $gatewayKey);
	}

	public function get(IUser $user, string $gatewayName): State {
		$isVerified = $this->getUserValue($user, $gatewayName, 'verified', 'false') === 'true';
		$identifier = $this->getUserValue($user, $gatewayName, 'identifier');
		$verificationCode = $this->getUserValue($user, $gatewayName, 'verification_code');

		if ($isVerified) {
			$state = SmsProvider::STATE_ENABLED;
		} elseif ($identifier !== '' && $verificationCode !== '') {
			$state = SmsProvider::STATE_VERIFYING;
		} else {
			$state = SmsProvider::STATE_DISABLED;
		}

		return new State(
			$user,
			$state,
			$gatewayName,
			$identifier,
			$verificationCode
		);
	}

	public function persist(State $state): State {
		switch ($state->getState()) {
			case SmsProvider::STATE_DISABLED:
				$this->deleteUserValue(
					$state->getUser(),
					$state->getGatewayName(),
					'verified'
				);
				$this->deleteUserValue(
					$state->getUser(),
					$state->getGatewayName(),
					'verification_code'
				);

				break;
			case SmsProvider::STATE_VERIFYING:
				$this->setUserValue(
					$state->getUser(),
					$state->getGatewayName(),
					'identifier',
					$state->getIdentifier()
				);
				$this->setUserValue(
					$state->getUser(),
					$state->getGatewayName(),
					'verification_code',
					$state->getVerificationCode()
				);
				$this->setUserValue(
					$state->getUser(),
					$state->getGatewayName(),
					'verified',
					'false'
				);

				break;
			case SmsProvider::STATE_ENABLED:
				$this->setUserValue(
					$state->getUser(),
					$state->getGatewayName(),
					'identifier',
					$state->getIdentifier()
				);
				$this->setUserValue(
					$state->getUser(),
					$state->getGatewayName(),
					'verification_code',
					$state->getVerificationCode()
				);
				$this->setUserValue(
					$state->getUser(),
					$state->getGatewayName(),
					'verified',
					'true'
				);

				break;
			default:
				throw new Exception('invalid provider state');
		}

		return $state;
	}
}
