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
use OCA\TwoFactorGateway\Provider\State;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\IUser;
use OCP\Security\ISecureRandom;

class SetupService {

	/** @var StateStorage */
	private $stateStorage;

	/** @var IGateway */
	private $smsService;

	/** @var ISecureRandom */
	private $random;

	public function __construct(StateStorage $stateStorage,
								IGateway $smsService,
								ISecureRandom $random) {
		$this->stateStorage = $stateStorage;
		$this->smsService = $smsService;
		$this->random = $random;
	}

	public function getState(IUser $user): State {
		return $this->stateStorage->get($user);
	}

	/**
	 * @throws IdentifierMissingException
	 */
	public function getChallengePhoneNumber(IUser $user): string {
		$state = $this->stateStorage->get($user);
		$identifier = $state->getIdentifier();
		if (is_null($identifier)) {
			throw new IdentifierMissingException('verified identifier is missing');
		}

		return $identifier;
	}

	/**
	 * Send out confirmation message and save current identifier in user settings
	 */
	public function startSetup(IUser $user, string $identifier): State {
		$verificationNumber = $this->random->generate(6, ISecureRandom::CHAR_DIGITS);
		try {
			$this->smsService->send($user, $identifier, "$verificationNumber is your Nextcloud verification code.");
		} catch (SmsTransmissionException $ex) {
			throw new VerificationTransmissionException('could not send verification code');
		}

		return $this->stateStorage->persist(
			State::verifying($user, $this->smsService->getShortName(), $identifier, $verificationNumber)
		);
	}

	public function finishSetup(IUser $user, string $token): State {
		$state = $this->stateStorage->get($user);
		if (is_null($state->getVerificationCode())) {
			throw new Exception('no verification code set');
		}

		if ($state->getVerificationCode() !== $token) {
			throw new VerificationException('verification token mismatch');
		}

		return $this->stateStorage->persist(
			$state->verify()
		);
	}

	public function disable(IUser $user): State {
		return $this->stateStorage->persist(
			State::disabled($user)
		);
	}

}
