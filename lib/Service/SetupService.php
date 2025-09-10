<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use Exception;
use OCA\TwoFactorGateway\Exception\IdentifierMissingException;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\Exception\VerificationException;
use OCA\TwoFactorGateway\Exception\VerificationTransmissionException;
use OCA\TwoFactorGateway\Provider\Factory;
use OCA\TwoFactorGateway\Provider\State;
use OCA\TwoFactorGateway\Service\Gateway\Factory as GatewayFactory;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\IUser;
use OCP\Security\ISecureRandom;

class SetupService {
	public function __construct(
		private StateStorage $stateStorage,
		private GatewayFactory $gatewayFactory,
		private Factory $providerFactory,
		private ISecureRandom $random,
		private IRegistry $providerRegistry,
	) {
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
