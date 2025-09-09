<?php

declare(strict_types=1);

/**
 * @author Rainer Dohmen <rdohmen@pensionmoselblick.de>
 *
 * Nextcloud - Two-factor Gateway for XMPP
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

namespace OCA\TwoFactorGateway\Service\Gateway\XMPP;

use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\Gateway\IGatewayConfig;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class Gateway implements IGateway {

	/** @var IClient */
	private $client;

	/** @var GatewayConfig */
	private $gatewayConfig;

	/** @var IConfig */
	private $config;

	public function __construct(
		IClientService $clientService,
		GatewayConfig $gatewayConfig,
		IConfig $config,
		private LoggerInterface $logger,
	) {
		$this->client = $clientService->newClient();
		$this->gatewayConfig = $gatewayConfig;
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * @param IUser $user
	 * @param string $identifier
	 * @param string $message
	 *
	 * @throws SmsTransmissionException
	 */
	public function send(IUser $user, string $identifier, string $message) {
		$this->logger->debug("sending xmpp message to $identifier, message: $message");

		$sender = $this->gatewayConfig->getSender();
		$password = $this->gatewayConfig->getPassword();
		$server = $this->gatewayConfig->getServer();
		$method = $this->gatewayConfig->getMethod();
		$user = $this->gatewayConfig->getUsername();
		$url = $server . $identifier;

		if ($method === '1') {
			$from = $user;
		}
		if ($method === '2') {
			$from = $sender;
		}
		$this->logger->debug("URL: $url, sender: $sender, method: $method");

		try {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
			curl_setopt($ch, CURLOPT_USERPWD, $from . ':' . $password);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/plain']);
			$result = curl_exec($ch);
			curl_close($ch);
			$this->logger->debug("XMPP message to $identifier sent");
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * Get the gateway-specific configuration
	 *
	 * @return IGatewayConfig
	 */
	public function getConfig(): IGatewayConfig {
		return $this->gatewayConfig;
	}
}
