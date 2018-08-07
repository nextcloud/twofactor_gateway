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

use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\PhoneNumberMask;
use OCA\TwoFactorGateway\Service\IGateway;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\IL10N;
use OCP\ISession;
use OCP\IUser;
use OCP\Security\ISecureRandom;
use OCP\Template;

class SmsProvider implements IProvider {

	const STATE_DISABLED = 0;
	const STATE_START_VERIFICATION = 1;
	const STATE_VERIFYING = 2;
	const STATE_ENABLED = 3;
	const SESSION_KEY = 'twofactor_gateway_secret';

	/** @var IGateway */
	private $gateway;

	/** @var StateStorage */
	private $stateStorage;

	/** @var ISession */
	private $session;

	/** @var ISecureRandom */
	private $secureRandom;

	/** @var IL10N */
	private $l10n;

	public function __construct(IGateway $gateway,
								StateStorage $stateStorage,
								ISession $session,
								ISecureRandom $secureRandom,
								IL10N $l10n) {
		$this->gateway = $gateway;
		$this->stateStorage = $stateStorage;
		$this->session = $session;
		$this->secureRandom = $secureRandom;
		$this->l10n = $l10n;
	}

	/**
	 * Get unique identifier of this 2FA provider
	 */
	public function getId(): string {
		return 'gateway';
	}

	/**
	 * Get the display name for selecting the 2FA provider
	 *
	 * @todo use gateway-specific display name
	 */
	public function getDisplayName(): string {
		return $this->l10n->t('Message gateway verification');
	}

	/**
	 * Get the description for selecting the 2FA provider
	 *
	 * @todo use gateway-specific description
	 */
	public function getDescription(): string {
		return $this->l10n->t('Send an authentication code via a messaging Gateway');
	}

	private function getSecret(): string {
		if ($this->session->exists(self::SESSION_KEY)) {
			return $this->session->get(self::SESSION_KEY);
		}

		$secret = $this->secureRandom->generate(6, ISecureRandom::CHAR_DIGITS);
		$this->session->set(self::SESSION_KEY, $secret);

		return $secret;
	}

	/**
	 * Get the template for rending the 2FA provider view
	 */
	public function getTemplate(IUser $user): Template {
		$secret = $this->getSecret();

		try {
			$identifier = $this->stateStorage->get($user)->getIdentifier();
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
		$valid = $this->session->exists(self::SESSION_KEY)
			&& $this->session->get(self::SESSION_KEY) === $challenge;

		if ($valid) {
			$this->session->remove(self::SESSION_KEY);
		}

		return $valid;
	}

	/**
	 * Decides whether 2FA is enabled for the given user
	 */
	public function isTwoFactorAuthEnabledForUser(IUser $user): bool {
		return $this->stateStorage->get($user)->getState() === self::STATE_ENABLED;
	}

}
