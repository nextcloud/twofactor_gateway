<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp;

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
	private const DEVICES_ENDPOINT = '/devices';

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IClientService $clientService,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Returns the device state using the most precise available source.
	 *
	 * For configured devices, it first queries /devices/{id}/status to avoid
	 * loading the full devices list on every poll. It falls back to /devices
	 * for backward compatibility and richer state resolution.
	 */
	public function fetch(string $baseUrl): string {
		$deviceId = $this->appConfig->getValueString(
			Application::APP_ID,
			self::APPCONFIG_KEY_DEVICE_ID,
			'',
		);

		if ($deviceId !== '') {
			$statusState = $this->fetchFromStatusEndpoint($baseUrl, $deviceId);
			if ($statusState !== null) {
				return $statusState;
			}
		}

		return $this->fetchFromDevicesEndpoint($baseUrl, $deviceId);
	}

	/**
	 * Uses /devices/{id}/status when possible.
	 *
	 * Returns null to signal that caller should fall back to /devices
	 * (for older GoWhatsApp versions or ambiguous disconnected states).
	 */
	private function fetchFromStatusEndpoint(string $baseUrl, string $deviceId): ?string {
		try {
			$client = $this->clientService->newClient();
			$response = $client->get(
				$baseUrl . self::DEVICES_ENDPOINT . '/' . rawurlencode($deviceId) . '/status',
				['timeout' => 5],
			);
			$body = (string)$response->getBody();
			$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

			if (($data['code'] ?? '') !== 'SUCCESS' || !isset($data['results'])) {
				$this->logger->debug('GoWhatsApp /devices/{id}/status returned non-SUCCESS; falling back to /devices.', [
					'device_id' => $deviceId,
				]);
				return null;
			}

			$isLoggedIn = (bool)($data['results']['is_logged_in'] ?? false);
			if ($isLoggedIn) {
				return 'logged_in';
			}

			$isConnected = (bool)($data['results']['is_connected'] ?? false);
			if ($isConnected) {
				return 'connected';
			}

			// Ambiguous state (both false): let /devices decide logged_out vs disconnected.
			return null;
		} catch (\Throwable $e) {
			$this->logger->debug('GoWhatsApp /devices/{id}/status unavailable; falling back to /devices.', [
				'device_id' => $deviceId,
				'exception' => $e,
			]);
			return null;
		}
	}

	private function fetchFromDevicesEndpoint(string $baseUrl, string $deviceId): string {

		try {
			$client = $this->clientService->newClient();
			$options = ['timeout' => 5];
			if ($deviceId !== '') {
				$options['headers'] = ['X-Device-Id' => $deviceId];
			}

			$response = $client->get($baseUrl . self::DEVICES_ENDPOINT, $options);
			$body = (string)$response->getBody();
			$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

			if (($data['code'] ?? '') !== 'SUCCESS' || !array_key_exists('results', $data)) {
				$this->logger->info('GoWhatsApp /devices returned non-SUCCESS.', ['body' => $body]);
				return 'disconnected';
			}

			if ($deviceId !== '' && (!is_array($data['results']) || $data['results'] === [])) {
				$this->logger->warning('GoWhatsApp device list is empty for configured device_id; treating as logged_out.', [
					'device_id' => $deviceId,
				]);
				return 'logged_out';
			}

			$devices = is_array($data['results']) ? $data['results'] : [];
			return $this->resolveDeviceState($devices, $deviceId);
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
