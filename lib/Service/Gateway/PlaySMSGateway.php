<?php

declare(strict_types=1);

/**
 * @author Pascal Clémot <pascal.clemot@free.fr>
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

namespace OCA\TwoFactorGateway\Service\Gateway;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\Service\IGateway;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;

class PlaySMSGateway implements IGateway {

	/** @var IClient */
	private $client;

	/** @var IConfig */
	private $config;

	/** @var IL10N */
	private $l10n;

	public function __construct(IClientService $clientService,
								IConfig $config,
								IL10N $l10n) {
		$this->client = $clientService->newClient();
		$this->config = $config;
		$this->l10n = $l10n;
	}

	/**
	 * @param IUser $user
	 * @param string $idenfier
	 * @param string $message
	 *
	 * @throws SmsTransmissionException
	 */
	public function send(IUser $user, string $idenfier, string $message) {
		$url = $this->config->getAppValue('twofactor_gateway', 'playsms_url');
		$user = $this->config->getAppValue('twofactor_gateway', 'playsms_user');
		$password = $this->config->getAppValue('twofactor_gateway', 'playsms_password');
		try {
			$this->client->get($url, [
				'query' => [
					'app' => 'ws',
					'u' => $user,
					'h' => $password,
					'op' => 'pv',
					'to' => $idenfier,
					'msg' => $message,
				],
			]);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * Get a short description of this gateway's name so that users know how
	 * their messages are delivered, e.g. "Telegram"
	 *
	 * @return string
	 */
	public function getShortName(): string {
		return 'SMS';
	}

	/**
	 * @return string
	 */
	public function getProviderDescription(): string {
		return $this->l10n->t('Authenticate via SMS');
	}
}
