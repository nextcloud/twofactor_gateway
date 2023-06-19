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
use OCP\AppFramework\Utility\ITimeFactory;
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

	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(
		IClientService $clientService,
		GatewayConfig $config,
		ILogger $logger,
		ITimeFactory $timeFactory,
	) {
		$this->clientService = $clientService;
		$this->config = $config;
		$this->logger = $logger;
		$this->timeFactory = $timeFactory;
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
		$response = $client->post(
			$this->config->getUrl() . '/api/v1/rpc',
			[
				'http_errors' => false,
				'json' => [
					'jsonrpc' => '2.0',
					'method' => 'version',
					'id' => 'version_' . $this->timeFactory->getTime(),
				],
			]);
		if ($response->getStatusCode() === 200 || $response->getStatusCode() === 201) {
			// native signal-cli JSON RPC. The 201 "created" is probably a bug.
			$response = $response = $client->post(
				$this->config->getUrl() . '/api/v1/rpc',
				[
					'json' => [
						'jsonrpc' => '2.0',
						'method' => 'send',
						'id' => 'code_' . $this->timeFactory->getTime(),
						'params' => [
							'recipient' => $identifier,
							'message' => $message,
							'account' => $this->config->getAccount(),
						],
					],
				]);
			$body = $response->getBody();
			$json = json_decode($body, true);
			$statusCode = $response->getStatusCode();
			if ($statusCode < 200 || $statusCode >= 300 || is_null($json) || !is_array($json) || ($json['jsonrpc'] ?? null) != '2.0' || !isset($json['result']['timestamp'])) {
				throw new SmsTransmissionException("error reported by Signal gateway, status=$statusCode, body=$body}");
			}
		} else {
			$response = $client->get(
				$this->config->getUrl() . '/v1/about',
				[
					'http_errors' => false,
				],
			);
			if ($response->getStatusCode() === 200) {
				// New style gateway
				// https://gitlab.com/morph027/signal-cli-dbus-rest-api
				// https://gitlab.com/morph027/python-signal-cli-rest-api
				// https://bbernhard.github.io/signal-cli-rest-api/
				$body = $response->getBody();
				$json = json_decode($body, true);
				$versions = $json['versions'] || [];
				if (is_array($versions) && in_array('v2', $versions)) {
					$response = $client->post(
						$this->config->getUrl() . '/v2/send',
						[
							'json' => [
								'recipients' => $identifier,
								'message' => $message,
								'account' => $this->config->getAccount(),
							],
						]
					);
				} else {
					$response = $client->post(
						$this->config->getUrl() . '/v1/send/' . $identifier,
						[
							'json' => [ 'message' => $message ],
						]
					);
				}
				$body = $response->getBody();
				$json = json_decode($body, true);
				$statusCode = $response->getStatusCode();
				if ($statusCode !== 201 || is_null($json) || !is_array($json) || (!isset($json['timestamps']) && !isset($json['timestamp']))) {
					throw new SmsTransmissionException("error reported by Signal gateway, status=$statusCode, body=$body}");
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
				$statusCode = $response->getStatusCode();

				if ($statusCode !== 200 || is_null($json) || !is_array($json) || !isset($json['success']) || $json['success'] !== true) {
					throw new SmsTransmissionException("error reported by Signal gateway, status=$statusCode, body=$body}");
				}
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
