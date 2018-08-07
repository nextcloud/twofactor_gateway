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

namespace OCA\TwoFactorGateway\Service\Gateway;

use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\Service\IGateway;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUser;

/**
 * An integration of https://gitlab.com/morph027/signal-web-gateway
 */
class SignalGateway implements IGateway {

	/** @var IClientService */
	private $clientService;

	/** @var ILogger */
	private $logger;

	/** @var IL10N */
	private $l10n;

	public function __construct(IClientService $clientService,
								ILogger $logger,
								IL10N $l10n) {
		$this->clientService = $clientService;
		$this->logger = $logger;
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
		// TODO: make configurable
		$endpoint = 'http://localhost:5000';

		$client = $this->clientService->newClient();
		$response = $client->post($endpoint, [
			'body' => [
				'to' => $idenfier,
				'message' => $message,
			],
		]);
		$body = $response->getBody();
		$json = json_decode($body, true);

		if ($response->getStatusCode() !== 200 || is_null($json) || !is_array($json) || !isset($json['success']) || $json['success'] !== true) {
			$status = $response->getStatusCode();
			throw new SmsTransmissionException("error reported by Signal gateway, status=$status, body=$body}");
		}
	}

	/**
	 * Get a short description of this gateway's name so that users know how
	 * their messages are delivered, e.g. "Telegram"
	 *
	 * @return string
	 */
	public function getShortName(): string {
		return 'Signal';
	}

	/**
	 * @return string
	 */
	public function getProviderDescription(): string {
		return $this->l10n->t('Authenticate via Signal');
	}
}
