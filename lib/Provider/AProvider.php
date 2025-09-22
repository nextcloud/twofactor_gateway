<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\PhoneNumberMask;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCA\TwoFactorGateway\Settings\PersonalSettings;
use OCP\AppFramework\Services\IInitialState;
use OCP\Authentication\TwoFactorAuth\IDeactivatableByAdmin;
use OCP\Authentication\TwoFactorAuth\IPersonalProviderSettings;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IProvidesIcons;
use OCP\Authentication\TwoFactorAuth\IProvidesPersonalSettings;
use OCP\IL10N;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Security\ISecureRandom;
use OCP\Server;
use OCP\Template;

abstract class AProvider implements IProvider, IProvidesIcons, IDeactivatableByAdmin, IProvidesPersonalSettings {

	protected string $gatewayName = '';
	protected IGateway $gateway;

	private function getSessionKey(): string {
		return 'twofactor_gateway_' . $this->getGatewayName() . '_secret';
	}

	public function __construct(
		protected GatewayFactory $gatewayFactory,
		protected StateStorage $stateStorage,
		protected ISession $session,
		protected ISecureRandom $secureRandom,
		protected IL10N $l10n,
		protected IInitialState $initialState,
	) {
		$this->gateway = $this->gatewayFactory->get($this->getGatewayName());
	}

	private function getGatewayName(): string {
		if ($this->gatewayName) {
			return $this->gatewayName;
		}
		$fqcn = static::class;
		$parts = explode('\\', $fqcn);
		[$name] = array_slice($parts, -2, 1);
		$this->gatewayName = strtolower($name);
		return $this->gatewayName;
	}

	#[\Override]
	public function getId(): string {
		return 'gateway_' . $this->getGatewayName();
	}

	private function getSecret(): string {
		if ($this->session->exists($this->getSessionKey())) {
			return $this->session->get($this->getSessionKey());
		}

		$secret = $this->secureRandom->generate(6, ISecureRandom::CHAR_DIGITS);
		$this->session->set($this->getSessionKey(), $secret);

		return $secret;
	}

	#[\Override]
	public function getTemplate(IUser $user): Template {
		$secret = $this->getSecret();

		try {
			$identifier = $this->stateStorage->get($user, $this->getGatewayName())->getIdentifier() ?? '';
			$this->gateway->send(
				$identifier,
				$this->l10n->t('%s is your Nextcloud authentication code', [
					$secret
				]),
				['code' => $secret],
			);
		} catch (MessageTransmissionException) {
			return new Template('twofactor_gateway', 'error');
		}

		$tmpl = new Template('twofactor_gateway', 'challenge');
		$tmpl->assign('phone', PhoneNumberMask::maskNumber($identifier));
		return $tmpl;
	}

	#[\Override]
	public function verifyChallenge(IUser $user, string $challenge): bool {
		$valid = $this->session->exists($this->getSessionKey())
			&& $this->session->get($this->getSessionKey()) === $challenge;

		if ($valid) {
			$this->session->remove($this->getSessionKey());
		}

		return $valid;
	}

	#[\Override]
	public function isTwoFactorAuthEnabledForUser(IUser $user): bool {
		return $this->stateStorage->get($user, $this->getGatewayName())->getState() === StateStorage::STATE_ENABLED;
	}

	#[\Override]
	public function getPersonalSettings(IUser $user): IPersonalProviderSettings {
		$this->initialState->provideInitialState('settings-' . $this->gateway->getProviderId(), $this->gateway->getSettings());
		return new PersonalSettings(
			$this->getGatewayName(),
			$this->gateway->isComplete(),
		);
	}

	#[\Override]
	public function getLightIcon(): String {
		return Server::get(IURLGenerator::class)->imagePath(Application::APP_ID, 'app.svg');
	}

	#[\Override]
	public function getDarkIcon(): String {
		return Server::get(IURLGenerator::class)->imagePath(Application::APP_ID, 'app-dark.svg');
	}

	#[\Override]
	public function disableFor(IUser $user) {
		$state = $this->stateStorage->get($user, $this->getGatewayName());
		if ($state->getState() === StateStorage::STATE_ENABLED) {
			$this->stateStorage->persist($state->disabled($user, $this->getGatewayName()));
		}
	}
}
