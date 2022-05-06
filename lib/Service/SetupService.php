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
use OCA\TwoFactorGateway\Exception\IdentifierMissingException;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\Exception\VerificationException;
use OCA\TwoFactorGateway\Exception\VerificationTransmissionException;
use OCA\TwoFactorGateway\Provider\Factory;
use OCA\TwoFactorGateway\Service\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\State;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\IUser;
use OCP\Security\ISecureRandom;

class SetupService {

	/** @var StateStorage */
	private $stateStorage;

	/** @var GatewayFactory */
	private $gatewayFactory;

	/** @var Factory */
	private $providerFactory;

	/** @var ISecureRandom */
	private $random;

	/** @var IRegistry */
	private $providerRegistry;

	public function __construct(StateStorage $stateStorage,
								GatewayFactory $gatewayFactory,
								Factory $providerFactory,
								ISecureRandom $random,
								IRegistry $providerRegistry) {
		$this->stateStorage = $stateStorage;
		$this->gatewayFactory = $gatewayFactory;
		$this->providerFactory = $providerFactory;
		$this->random = $random;
		$this->providerRegistry = $providerRegistry;
	}

	public function getState(IUser $user, string $gatewayName): State {
		return $this->stateStorage->get($user, $gatewayName);
	}

	/**
	 * @throws IdentifierMissingException
	 */
	public function getChallengePhoneNumber(IUser $user, string $gatewayName): string {
		$state = $this->stateStorage->get($user, $gatewayName);
		$identifier = $state->getIdentifier();
		if (is_null($identifier)) {
			throw new IdentifierMissingException("verified identifier for $gatewayName is missing");
		}

		return $identifier;
	}

	/**
	 * Send out confirmation message and save current identifier in user settings
	 */
	public function startSetup(IUser $user, string $gatewayName, string $identifier): State {
		$verificationNumber = $this->random->generate(6, ISecureRandom::CHAR_DIGITS);
		$gateway = $this->gatewayFactory->getGateway($gatewayName);
		try {
			$gateway->send($user, $identifier, "$verificationNumber is your Nextcloud verification code.");
		} catch (SmsTransmissionException $ex) {
			throw new VerificationTransmissionException('could not send verification code', $ex->getCode(), $ex);
		}

		return $this->stateStorage->persist(
			State::verifying($user, $gatewayName, $identifier, $verificationNumber)
		);
	}

	public function finishSetup(IUser $user, string $gatewayName, string $token): State {
		$state = $this->stateStorage->get($user, $gatewayName);
		if (is_null($state->getVerificationCode())) {
			throw new Exception('no verification code set');
		}

		if ($state->getVerificationCode() !== $token) {
			throw new VerificationException('verification token mismatch');
		}

		$provider = $this->providerFactory->getProvider($gatewayName);
		$this->providerRegistry->enableProviderFor($provider, $user);

		return $this->stateStorage->persist(
			$state->verify()
		);
	}

	public function disable(IUser $user, string $gatewayName): State {
		$provider = $this->providerFactory->getProvider($gatewayName);
		$this->providerRegistry->enableProviderFor($provider, $user);
		$this->providerRegistry->disableProviderFor($provider, $user);


		return $this->stateStorage->persist(
			State::disabled($user, $gatewayName)
		);
	}
}
