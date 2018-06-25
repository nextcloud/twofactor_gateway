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
use OCA\TwoFactorGateway\Exception\IdentifierMissingException;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\Exception\VerificationException;
use OCA\TwoFactorGateway\Exception\VerificationTransmissionException;
use OCA\TwoFactorGateway\Provider\SmsProvider;
use OCA\TwoFactorGateway\Provider\State;
use OCP\IConfig;
use OCP\IUser;
use OCP\Security\ISecureRandom;

class SetupService {

	/** @var IConfig */
	private $config;


	/** @var ISmsService */
	private $smsService;

	/** @var ISecureRandom */
	private $random;

	public function __construct(IConfig $config,
								ISmsService $smsService, ISecureRandom $random) {
		$this->config = $config;

		$this->smsService = $smsService;
		$this->random = $random;
	}

	public function getState(IUser $user): State {
		$isVerified = $this->config->getUserValue($user->getUID(), Application::APP_NAME, 'verified', 'false') === 'true';
		$state = $isVerified ? SmsProvider::STATE_ENABLED : SmsProvider::STATE_DISABLED;
		$verifiedNumber = $this->config->getUserValue($user->getUID(), 'twofactor_gateway', 'phone', null);

		return new State($state, $verifiedNumber);
	}

	/**
	 * @throws IdentifierMissingException
	 */
	public function getChallengePhoneNumber(IUser $user): string {
		$verifiedNumber = $this->config->getUserValue($user->getUID(), 'twofactor_gateway', 'identifier', null);
		if (is_null($verifiedNumber)) {
			throw new IdentifierMissingException('verified identifier is missing');
		}

		return $verifiedNumber;
	}

	/**
	 * Send out confirmation message and save current identifier in user settings
	 */
	public function startSetup(IUser $user, string $identifier): string {
		$verificationNumber = $this->random->generate(6, ISecureRandom::CHAR_DIGITS);
		try {
			$this->smsService->send($user, $identifier, "$verificationNumber is your Nextcloud verification code.");
		} catch (SmsTransmissionException $ex) {
			throw new VerificationTransmissionException('could not send verification code');
		}
		$this->config->setUserValue($user->getUID(), Application::APP_NAME, 'identifier', $identifier);
		$this->config->setUserValue($user->getUID(), Application::APP_NAME, 'verification_code', $verificationNumber);
		$this->config->setUserValue($user->getUID(), Application::APP_NAME, 'verified', 'false');

		return $identifier;
	}

	public function finishSetup(IUser $user, string $token) {
		$verificationNumber = $this->config->getUserValue($user->getUID(),
			Application::APP_NAME, 'verification_code', null);
		if (is_null($verificationNumber)) {
			throw new Exception('no verification code set');
		}

		if ($verificationNumber !== $token) {
			throw new VerificationException('verification token mismatch');
		}

		$this->config->setUserValue($user->getUID(), Application::APP_NAME,
			'verified', 'true');
	}

	public function disable(IUser $user) {
		$this->config->deleteUserValue($user->getUID(), Application::APP_NAME, 'verified');
		$this->config->deleteUserValue($user->getUID(), Application::APP_NAME, 'verification_code');

		return $this->getState($user);
	}

}
