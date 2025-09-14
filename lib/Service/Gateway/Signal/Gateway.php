<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\Signal;

use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCP\Http\Client\IClientService;
use OCP\IUser;
use Psr\Log\LoggerInterface;

/**
 * An integration of https://gitlab.com/morph027/signal-web-gateway
 */
class Gateway implements IGateway {

	public function __construct(
		private IClientService $clientService,
		public GatewayConfig $gatewayConfig,
		private LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function send(IUser $user, string $identifier, string $message, array $extra = []) {
		$client = $this->clientService->newClient();
		// determine type of gateway
		$response = $client->get($this->gatewayConfig->getUrl() . '/v1/about');
		if ($response->getStatusCode() === 200) {
			// New style gateway https://gitlab.com/morph027/signal-cli-dbus-rest-api
			$response = $client->post(
				$this->gatewayConfig->getUrl() . '/v1/send/' . $identifier,
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
				$this->gatewayConfig->getUrl() . '/v1/send/' . $identifier,
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
}
