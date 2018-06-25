<?php

declare(strict_types = 1);

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
use OC\Accounts\AccountManager;
use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\PhoneNumberMismatchException;
use OCA\TwoFactorGateway\Exception\PhoneNumberMissingException;
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

	/** @var AccountManager */
	private $accountManager;

	/** @var ISmsService */
	private $smsService;

	/** @var ISecureRandom */
	private $random;

	public function __construct(IConfig $config, AccountManager $accountManager,
		ISmsService $smsService, ISecureRandom $random) {
		$this->config = $config;
		$this->accountManager = $accountManager;
		$this->smsService = $smsService;
		$this->random = $random;
	}

	public function getState(IUser $user): State {
		$state = $this->config->getUserValue($user->getUID(), Application::APP_NAME,
				'verified', 'false') === 'true' ? SmsProvider::STATE_ENABLED : SmsProvider::STATE_DISABLED;
		$verifiedNumber = $this->config->getUserValue($user->getUID(),
			'twofactor_gateway', 'phone', null);

		return new State($state, $verifiedNumber);
	}

	/**
	 * @throws PhoneNumberMissingException
	 */
	public function getChallengePhoneNumber(IUser $user): string {
		$numerFromUserData = $this->getVerificationPhoneNumber($user);
		$verifiedNumber = $this->config->getUserValue($user->getUID(),
			'twofactor_gateway', 'phone', null);
		if (is_null($verifiedNumber)) {
			throw new PhoneNumberMissingException('verified phone number is missing');
		}

		if ($numerFromUserData !== $verifiedNumber) {
			throw new PhoneNumberMismatchException('user\'s phone number has change');
		}

		return $verifiedNumber;
	}

	/**
	 * @throws PhoneNumberMissingException
	 */
	private function getVerificationPhoneNumber(IUser $user): string {
		$userData = $this->accountManager->getUser($user);

		if (!isset($userData[AccountManager::PROPERTY_PHONE]) || empty($userData[AccountManager::PROPERTY_PHONE])) {
			throw new PhoneNumberMissingException('user did not set a phone number');
		}

		$num = $userData[AccountManager::PROPERTY_PHONE]['value'];

		if (is_null($num) || empty($num)) {
			throw new PhoneNumberMissingException('phone number is empty');
		}

		return $num;
	}

	/**
	 * Send out confirmation message and save current phone number in user settings
	 *
	 * @throws PhoneNumberMissingException
	 */
	public function startSetup(IUser $user): string {
		$phoneNumber = $this->getVerificationPhoneNumber($user);

		$verificationNumber = $this->random->generate(6, ISecureRandom::CHAR_DIGITS);
		try {
			$this->smsService->send(, $phoneNumber, "$verificationNumber is your Nextcloud verification code.");
		} catch (SmsTransmissionException $ex) {
			throw new VerificationTransmissionException('could not send verification code');
		}
		$this->config->setUserValue($user->getUID(), Application::APP_NAME, 'phone',
			$phoneNumber);
		$this->config->setUserValue($user->getUID(), Application::APP_NAME,
			'verification_code', $verificationNumber);
		$this->config->setUserValue($user->getUID(), Application::APP_NAME,
			'verified', 'false');

		return $phoneNumber;
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
		$this->config->deleteUserValue($user->getUID(), Application::APP_NAME,
			'verified');
		$this->config->deleteUserValue($user->getUID(), Application::APP_NAME,
			'verification_code');

		return $this->getState($user);
	}

}
