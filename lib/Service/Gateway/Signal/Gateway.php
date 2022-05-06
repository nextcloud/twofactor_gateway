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

namespace OCA\TwoFactorGateway\Service\Gateway\Signal;

use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\Gateway\IGatewayConfig;
use OCP\Http\Client\IClientService;
use OCP\ILogger;
use OCP\IUser;

/**
 * An integration of https://gitlab.com/morph027/signal-web-gateway
 */
class Gateway implements IGateway {

	/** @var IClientService */
	private $clientService;

	/** @var GatewayConfig */
	private $config;

	/** @var ILogger */
	private $logger;

	public function __construct(IClientService $clientService,
								GatewayConfig $config,
								ILogger $logger) {
		$this->clientService = $clientService;
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
		$client = $this->clientService->newClient();
		// determine type of gateway
		$response = $client->get($this->config->getUrl() . '/v1/about');
		if ($response->getStatusCode() === 200) {
			// New style gateway https://gitlab.com/morph027/signal-cli-dbus-rest-api
			$response = $client->post(
				$this->config->getUrl() . '/v1/send/' . $identifier,
				[
					'json' => [ 'message' => $message ],
				]
			);
			$body = $response->getBody();
			$json = json_decode($body, true);
			if ($response->getStatusCode() !== 201 || is_null($json) || !is_array($json) || !isset($json['timestamp'])) {
				$status = $response->getStatusCode();
				throw new SmsTransmissionException("error reported by Signal gateway, status=$status, body=$body}");
			}
		} else {
			// Try old deprecated gateway https://gitlab.com/morph027/signal-web-gateway
			$response = $client->post(
				$this->config->getUrl() . '/v1/send/' . $identifier,
				[
					'body' => [
						'to' => $identifier,
						'message' => $message,
					],
					'json' => [ 'message' => $message ],
				]
			);
			$body = $response->getBody();
			$json = json_decode($body, true);

			if ($response->getStatusCode() !== 200 || is_null($json) || !is_array($json) || !isset($json['success']) || $json['success'] !== true) {
				$status = $response->getStatusCode();
				throw new SmsTransmissionException("error reported by Signal gateway, status=$status, body=$body}");
			}
		}
	}

	/**
	 * Get the gateway-specific configuration
	 *
	 * @return IGatewayConfig
	 */
	public function getConfig(): IGatewayConfig {
		return $this->config;
	}
}
