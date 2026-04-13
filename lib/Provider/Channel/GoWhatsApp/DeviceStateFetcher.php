<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\GoWhatsApp;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Fetches the current device state from the GoWhatsApp HTTP API.
 *
 * This isolates all HTTP I/O and JSON parsing from the health monitoring
 * orchestration logic, making each side independently testable.
 *
 * Return values:
 * - A state string from the /devices response (e.g. 'connected', 'logged_in', 'disconnected')
 * - 'unreachable' when the API is down or the response is malformed
 * - 'logged_out' when the configured device ID is not present in the list
 * - 'disconnected' as the safe default for any ambiguous non-critical case
 */
class DeviceStateFetcher {
	private const APPCONFIG_KEY_DEVICE_ID = 'gowhatsapp_device_id';

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IClientService $clientService,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Contacts {$baseUrl}/devices and returns the device's current state string.
	 */
	public function fetch(string $baseUrl): string {
		$deviceId = $this->appConfig->getValueString(
			Application::APP_ID,
			self::APPCONFIG_KEY_DEVICE_ID,
			'',
		);

		try {
			$client = $this->clientService->newClient();
			$options = ['timeout' => 5];
			if ($deviceId !== '') {
				$options['headers'] = ['X-Device-Id' => $deviceId];
			}

			$response = $client->get($baseUrl . '/devices', $options);
			$body = (string)$response->getBody();
			$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

			if (($data['code'] ?? '') !== 'SUCCESS' || !isset($data['results'])) {
				$this->logger->info('GoWhatsApp /devices returned non-SUCCESS.', ['body' => $body]);
				return 'disconnected';
			}

			return $this->resolveDeviceState($data['results'], $deviceId);
		} catch (\JsonException $e) {
			$this->logger->error('GoWhatsApp /devices response is not valid JSON.', ['exception' => $e]);
			return 'unreachable';
		} catch (\Exception $e) {
			$this->logger->info('GoWhatsApp API unreachable during health check.', ['exception' => $e]);
			return 'unreachable';
		}
	}

	/**
	 * Selects the device state from the results array.
	 *
	 * When $deviceId is set, finds the matching entry or returns 'logged_out'.
	 * When $deviceId is empty, falls back to the first device in the list.
	 *
	 * @param array<array{id?: string, state?: string}> $devices
	 */
	private function resolveDeviceState(array $devices, string $deviceId): string {
		if ($deviceId !== '') {
			foreach ($devices as $device) {
				if (($device['id'] ?? '') === $deviceId) {
					return (string)($device['state'] ?? 'disconnected');
				}
			}
			$this->logger->warning('GoWhatsApp device_id not found in /devices response.', [
				'device_id' => $deviceId,
			]);
			return 'logged_out';
		}

		if (!empty($devices)) {
			return (string)($devices[0]['state'] ?? 'disconnected');
		}

		return 'disconnected';
	}
}
