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

namespace OCA\TwoFactorSms\Service;

use Exception;
use OC\Accounts\AccountManager;
use OCA\TwoFactorSms\AppInfo\Application;
use OCA\TwoFactorSms\Exception\PhoneNumberMissingException;
use OCA\TwoFactorSms\Exception\SmsTransmissionException;
use OCA\TwoFactorSms\Exception\VerificationException;
use OCA\TwoFactorSms\Exception\VerificationTransmissionException;
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

	public function __construct(IConfig $config, AccountManager $accountManager, ISmsService $smsService,
		ISecureRandom $random) {
		$this->config = $config;
		$this->accountManager = $accountManager;
		$this->smsService = $smsService;
		$this->random = $random;
	}

	/**
	 * @throws PhoneNumberMissingException
	 */
	private function getPhoneNumber(IUser $user): string {
		$userData = $this->accountManager->getUser($user);

		if (!isset($userData[AccountManager::PROPERTY_PHONE]) || empty($userData[AccountManager::PROPERTY_PHONE])) {
			throw new PhoneNumberMissingException();
		}

		return $userData[AccountManager::PROPERTY_PHONE];
	}

	/**
	 * Send out confirmation message and save current phone number in user settings
	 *
	 * @param IUser $user
	 * @throws PhoneNumberMissingException
	 */
	public function startSetup(IUser $user) {
		$phoneNumber = $this->getPhoneNumber($user);

		$verificationNumber = $this->random->generate(6, ISecureRandom::CHAR_DIGITS);
		try {
			$this->smsService->send($phoneNumber, "$verificationNumber is your Nextcloud verification code.");
		} catch (SmsTransmissionException $ex) {
			throw new VerificationTransmissionException('could not send verification code');
		}
		$this->config->setUserValue($user->getUID(), Application::APP_NAME, 'phone', $phoneNumber);
		$this->config->setUserValue($user->getUID(), Application::APP_NAME, 'verification_code', $verificationNumber);
		$this->config->setUserValue($user->getUID(), Application::APP_NAME, 'verified', false);
	}

	public function finishSetup(IUser $user, string $token) {
		$verificationNumber = $this->config->getUserValue($user->getUID(), Application::APP_NAME, 'verification_code', null);
		if (is_null($verificationNumber)) {
			throw new Exception('no verification code set');
		}

		if ($verificationNumber !== $token) {
			throw new VerificationException('verification token mismatch');
		}

		$this->config->setUserValue($user->getUID(), Application::APP_NAME, 'verified', true);
	}

}
