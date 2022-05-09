<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * Nextcloud - Two-factor Gateway
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\TwoFactorGateway\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\PhoneNumberMask;
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCA\TwoFactorGateway\Settings\PersonalSettings;
use OCP\Authentication\TwoFactorAuth\IDeactivatableByAdmin;
use OCP\Authentication\TwoFactorAuth\IPersonalProviderSettings;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IProvidesIcons;
use OCP\Authentication\TwoFactorAuth\IProvidesPersonalSettings;
use OCP\IL10N;
use OCP\ISession;
use OCP\IUser;
use OCP\Security\ISecureRandom;
use OCP\Template;

abstract class AProvider implements IProvider, IProvidesIcons, IDeactivatableByAdmin, IProvidesPersonalSettings {
	public const STATE_DISABLED = 0;
	public const STATE_START_VERIFICATION = 1;
	public const STATE_VERIFYING = 2;
	public const STATE_ENABLED = 3;

	/** @var string */
	protected $gatewayName;

	/** @var IGateway */
	protected $gateway;

	/** @var StateStorage */
	protected $stateStorage;

	/** @var ISession */
	protected $session;

	/** @var ISecureRandom */
	protected $secureRandom;

	/** @var IL10N */
	protected $l10n;

	private function getSessionKey() {
		return "twofactor_gateway_" . $this->gatewayName . "_secret";
	}

	public function __construct(string $gatewayId,
								IGateway $gateway,
								StateStorage $stateStorage,
								ISession $session,
								ISecureRandom $secureRandom,
								IL10N $l10n) {
		$this->gateway = $gateway;
		$this->gatewayName = $gatewayId;
		$this->stateStorage = $stateStorage;
		$this->session = $session;
		$this->secureRandom = $secureRandom;
		$this->l10n = $l10n;
	}

	/**
	 * Get unique identifier of this 2FA provider
	 */
	public function getId(): string {
		return "gateway_$this->gatewayName";
	}

	private function getSecret(): string {
		if ($this->session->exists($this->getSessionKey())) {
			return $this->session->get($this->getSessionKey());
		}

		$secret = $this->secureRandom->generate(6, ISecureRandom::CHAR_DIGITS);
		$this->session->set($this->getSessionKey(), $secret);

		return $secret;
	}

	/**
	 * Get the template for rending the 2FA provider view
	 */
	public function getTemplate(IUser $user): Template {
		$secret = $this->getSecret();

		try {
			$identifier = $this->stateStorage->get($user, $this->gatewayName)->getIdentifier();
			$this->gateway->send(
				$user,
				$identifier,
				$this->l10n->t('%s is your Nextcloud authentication code', [
					$secret
				])
			);
		} catch (SmsTransmissionException $ex) {
			return new Template('twofactor_gateway', 'error');
		}

		$tmpl = new Template('twofactor_gateway', 'challenge');
		$tmpl->assign('phone', PhoneNumberMask::maskNumber($identifier));
		return $tmpl;
	}

	/**
	 * Verify the given challenge
	 */
	public function verifyChallenge(IUser $user, string $challenge): bool {
		$valid = $this->session->exists($this->getSessionKey())
			&& $this->session->get($this->getSessionKey()) === $challenge;

		if ($valid) {
			$this->session->remove($this->getSessionKey());
		}

		return $valid;
	}

	/**
	 * Decides whether 2FA is enabled for the given user
	 */
	public function isTwoFactorAuthEnabledForUser(IUser $user): bool {
		return $this->stateStorage->get($user, $this->gatewayName)->getState() === self::STATE_ENABLED;
	}

	public function getPersonalSettings(IUser $user): IPersonalProviderSettings {
		return new PersonalSettings($this->gatewayName);
	}

	public function getLightIcon(): String {
		return image_path(Application::APP_ID, 'app.svg');
	}

	public function getDarkIcon(): String {
		return image_path(Application::APP_ID, 'app-dark.svg');
	}

	public function disableFor(IUser $user) {
		$state = $this->stateStorage->get($user, $this->gatewayName);
		if ($state->getState() === self::STATE_ENABLED) {
			$this->stateStorage->persist($state->disabled($user, $this->gatewayName));
		}
	}
}
