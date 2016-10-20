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

use Base32\Base32;
use OCA\TwoFactorSms\Exception\SmsTransmissionException;
use OCA\TwoFactorSms\Service\ISmsService;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ISession;
use OCP\IUser;
use OCP\Template;
use Otp\GoogleAuthenticator;
use Otp\Otp;

class SmsProvider implements IProvider {

	/** @var ISmsService */
	private $smsService;

	/** @var ISession */
	private $session;

	/** @var IConfig */
	private $config;

	/** @var IL10N */
	private $l10n;

	/**
	 * @param ISmsService $smsService
	 * @param ISession $session
	 * @param IConfig $config
	 * @param IL10N $l10n
	 */
	public function __construct(ISmsService $smsService, ISession $session, IConfig $config, IL10N $l10n) {
		$this->smsService = $smsService;
		$this->session = $session;
		$this->config = $config;
		$this->l10n = $l10n;
	}

	/**
	 * Get unique identifier of this 2FA provider
	 *
	 * @return string
	 */
	public function getId() {
		return 'sms';
	}

	/**
	 * Get the display name for selecting the 2FA provider
	 *
	 * @return string
	 */
	public function getDisplayName() {
		return $this->l10n->t('SMS verification');
	}

	/**
	 * Get the description for selecting the 2FA provider
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->l10n->t('Send a authentication code via SMS');
	}

	/**
	 * Get the template for rending the 2FA provider view
	 *
	 * @param IUser $user
	 * @return Template
	 */
	public function getTemplate(IUser $user) {
		$otp = new Otp();
		$secret = GoogleAuthenticator::generateRandom();
		$this->session->set('twofactor_sms_secret', $secret);
		$totp = $otp->totp(Base32::decode($secret));

		$phoneNumber = (int) $this->config->getUserValue($user->getUID(), 'twofactor_sms', 'phone');
		try {
			$this->smsService->send($phoneNumber, $this->l10n->t('%s is your Nextcloud authentication code', [$totp]));
		} catch (SmsTransmissionException $ex) {
			$tmpl = new Template('twofactor_sms', 'error');
			return $tmpl;
		}

		$tmpl = new Template('twofactor_sms', 'challenge');
		$tmpl->assign('phone', $this->protectPhoneNumber($phoneNumber));
		if ($this->config->getSystemValue('debug', false)) {
			$tmpl->assign('secret', $totp);
		}
		return $tmpl;
	}

	/**
	 * convert 123456789 to ******789
	 *
	 * @param string $number
	 * @return string
	 */
	private function protectPhoneNumber($number) {
		$length = strlen($number);
		$start = $length - 3;

		return str_repeat('*', $start) . substr($number, $start);
	}

	/**
	 * Verify the given challenge
	 *
	 * @param IUser $user
	 * @param string $challenge
	 */
	public function verifyChallenge(IUser $user, $challenge) {
		$otp = new Otp();
		$secret = $this->session->get('twofactor_sms_secret');
		return $otp->checkTotp(Base32::decode($secret), $challenge);
	}

	/**
	 * Decides whether 2FA is enabled for the given user
	 *
	 * @param IUser $user
	 * @return boolean
	 */
	public function isTwoFactorAuthEnabledForUser(IUser $user) {
		return $this->config->getUserValue($user->getUID(), 'twofactor_sms', null) !== null;
	}

}
