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
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCP\IConfig;
use OCP\IUser;

class StateStorage {

	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	public function get(IUser $user): State {
		$isVerified = $this->config->getUserValue($user->getUID(), Application::APP_NAME, 'verified', 'false') === 'true';
		$identifier = $this->config->getUserValue($user->getUID(), 'twofactor_gateway', 'identifier', null);
		$verificationCode = $this->config->getUserValue($user->getUID(), 'twofactor_gateway', 'verification_code', null);

		if ($isVerified) {
			$state = SmsProvider::STATE_ENABLED;
		} else if (!is_null($identifier) && !is_null($verificationCode)) {
			$state = SmsProvider::STATE_VERIFYING;
		} else {
			$state = SmsProvider::STATE_DISABLED;
		}

		return new State(
			$user,
			$state,
			'', // TODO: fix
			$identifier,
			$verificationCode
		);
	}

	public function persist(State $state): State {
		switch ($state->getState()) {
			case SmsProvider::STATE_DISABLED:
				$this->config->deleteUserValue(
					$state->getUser()->getUID(),
					Application::APP_NAME,
					'verified'
				);
				$this->config->deleteUserValue(
					$state->getUser()->getUID(),
					Application::APP_NAME,
					'verification_code'
				);

				break;
			case SmsProvider::STATE_VERIFYING:
				$this->config->setUserValue(
					$state->getUser()->getUID(),
					Application::APP_NAME,
					'identifier',
					$state->getIdentifier()
				);
				$this->config->setUserValue(
					$state->getUser()->getUID(),
					Application::APP_NAME,
					'verification_code',
					$state->getVerificationCode()
				);
				$this->config->setUserValue(
					$state->getUser()->getUID(),
					Application::APP_NAME,
					'verified',
					'false'
				);

				break;
			case SmsProvider::STATE_ENABLED:
				$this->config->setUserValue(
					$state->getUser()->getUID(),
					Application::APP_NAME,
					'identifier',
					$state->getIdentifier()
				);
				$this->config->setUserValue(
					$state->getUser()->getUID(),
					Application::APP_NAME,
					'verification_code',
					$state->getVerificationCode()
				);
				$this->config->setUserValue(
					$state->getUser()->getUID(),
					Application::APP_NAME,
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
