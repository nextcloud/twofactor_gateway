<?php

declare(strict_types=1);

/**
 * @author Juho Ylikorpi <juho.ylikorpi@node.solutions>
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

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class MesstoSms implements IProvider {

	const PROVIDER_ID = 'messtosms';

	/** @var IClient */
	private $client;

	/** @var MesstoSmsConfig */
	private $config;

	public function __construct(IClientService $clientService,
								MesstoSmsConfig $config) {
		$this->client = $clientService->newClient();
		$this->config = $config;
	}

	/**
	 * @param string $identifier
	 * @param string $message
	 *
	 * @throws SmsTransmissionException
	 */
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();
		$user = $config->getUser();
		$password = $config->getPassword();
		try {
			$this->client->post('https://www.messto.com/send', [
				'query' => [
					'sms_username' => $user,
					'sms_password' => $password,
					'sms_dest' => $identifier,
					'sms_text' => $message,
					'sms_class' => "0",
				],
			]);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return MesstoSmsConfig
	 */
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
