<?php

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * Nextcloud - Two-factor SMS
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

namespace OCA\TwoFactorSms\Provider;

use OCA\TwoFactorSms\Exception\SmsTransmissionException;
use OCA\TwoFactorSms\Service\ISmsService;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ISession;
use OCP\IUser;
use OCP\Security\ISecureRandom;
use OCP\Template;

class SmsProvider implements IProvider {

	const SESSION_KEY = 'twofactor_sms_secret';

	/** @var ISmsService */
	private $smsService;

	/** @var ISession */
	private $session;

	/** @var ISecureRandom */
	private $secureRandom;

	/** @var IConfig */
	private $config;

	/** @var IL10N */
	private $l10n;

	public function __construct(ISmsService $smsService, ISession $session, ISecureRandom $secureRandom, IConfig $config,
		IL10N $l10n) {
		$this->smsService = $smsService;
		$this->session = $session;
		$this->secureRandom = $secureRandom;
		$this->config = $config;
		$this->l10n = $l10n;
	}

	/**
	 * Get unique identifier of this 2FA provider
	 */
	public function getId(): string {
		return 'sms';
	}

	/**
	 * Get the display name for selecting the 2FA provider
	 */
	public function getDisplayName(): string {
		return $this->l10n->t('SMS verification');
	}

	/**
	 * Get the description for selecting the 2FA provider
	 */
	public function getDescription(): string {
		return $this->l10n->t('Send an authentication code via SMS');
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

		$phoneNumber = (int) $this->config->getUserValue($user->getUID(), 'twofactor_sms', 'phone');
		try {
			$this->smsService->send($phoneNumber, $this->l10n->t('%s is your Nextcloud authentication code', [$secret]));
		} catch (SmsTransmissionException $ex) {
			return new Template('twofactor_sms', 'error');
		}

		$tmpl = new Template('twofactor_sms', 'challenge');
		$tmpl->assign('phone', $this->protectPhoneNumber($phoneNumber));
		if ($this->config->getSystemValue('debug', false)) {
			$tmpl->assign('secret', $secret);
		}
		return $tmpl;
	}

	/**
	 * convert 123456789 to ******789
	 */
	private function protectPhoneNumber(string $number): string {
		$length = strlen($number);
		$start = $length - 3;

		return str_repeat('*', $start) . substr($number, $start);
	}

	/**
	 * Verify the given challenge
	 */
	public function verifyChallenge(IUser $user, string $challenge): bool {
		$valid = $this->session->exists(self::SESSION_KEY) && $this->session->get(self::SESSION_KEY) === $challenge;

		if ($valid) {
			$this->session->remove(self::SESSION_KEY);
		}

		return $valid;
	}

	/**
	 * Decides whether 2FA is enabled for the given user
	 */
	public function isTwoFactorAuthEnabledForUser(IUser $user): bool {
		return !is_null($this->config->getUserValue($user->getUID(), 'twofactor_sms', 'phone', null));
	}

}
