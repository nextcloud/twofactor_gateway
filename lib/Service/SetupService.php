<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use Exception;
use OCA\TwoFactorGateway\Exception\IdentifierMissingException;
use OCA\TwoFactorGateway\Exception\InvalidProviderException;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Exception\VerificationException;
use OCA\TwoFactorGateway\Provider\Factory as ProviderFactory;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\State;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\IL10N;
use OCP\IUser;
use OCP\Security\ISecureRandom;

class SetupService {
	public function __construct(
		private StateStorage $stateStorage,
		private GatewayFactory $gatewayFactory,
		private ProviderFactory $providerFactory,
		private ISecureRandom $random,
		private IRegistry $providerRegistry,
		private IL10N $l10n,
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
	 *
	 * @throws VerificationException
	 */
	public function startSetup(IUser $user, string $gatewayName, string $identifier): State {
		$verificationNumber = $this->random->generate(6, ISecureRandom::CHAR_DIGITS);
		$gateway = $this->gatewayFactory->get($gatewayName);
		try {
			$message = $gateway->getSettings()['allow_markdown'] ?? false
				? $this->l10n->t('`%s` is your verification code.', [$verificationNumber])
				: $this->l10n->t('%s is your verification code.', [$verificationNumber]);
			$gateway->send(
				$identifier,
				$message,
				['code' => $verificationNumber],
			);
		} catch (MessageTransmissionException $ex) {
			throw new VerificationException($ex->getMessage(), $ex->getCode(), $ex);
		}

		return $this->stateStorage->persist(
			State::verifying($user, $gatewayName, $identifier, $verificationNumber)
		);
	}

	public function finishSetup(IUser $user, string $gatewayName, string $token): State {
		$state = $this->stateStorage->get($user, $gatewayName);
		if (is_null($state->getVerificationCode())) {
			throw new VerificationException($this->l10n->t('no verification code set'));
		}

		if ($state->getVerificationCode() !== $token) {
			throw new VerificationException($this->l10n->t('verification token mismatch'));
		}

		try {
			$provider = $this->providerFactory->get($gatewayName);
		} catch (InvalidProviderException) {
			throw new VerificationException('Invalid provider');
		}
		$this->providerRegistry->enableProviderFor($provider, $user);

		try {
			return $this->stateStorage->persist(
				$state->verify()
			);
		} catch (Exception $e) {
			throw new VerificationException($e->getMessage());
		}
	}

	public function disable(IUser $user, string $gatewayName): State {
		try {
			$provider = $this->providerFactory->get($gatewayName);
		} catch (InvalidProviderException) {
			throw new VerificationException('Invalid provider');
		}
		$this->providerRegistry->enableProviderFor($provider, $user);
		$this->providerRegistry->disableProviderFor($provider, $user);

		try {
			return $this->stateStorage->persist(
				State::disabled($user, $gatewayName)
			);
		} catch (Exception $e) {
			throw new VerificationException($e->getMessage());
		}
	}
}
