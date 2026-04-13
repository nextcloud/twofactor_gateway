<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\GoWhatsApp;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Events\WhatsAppAuthenticationErrorEvent;
use OCA\TwoFactorGateway\Events\WhatsAppSessionWarningEvent;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Polls the GoWhatsApp API periodically, scores session health heuristically,
 * and dispatches either a WARNING or CRITICAL event when thresholds are reached.
 *
 * ### Health levels
 *
 * | Level    | Condition                                                 | Event dispatched                    |
 * |----------|-----------------------------------------------------------|-------------------------------------|
 * | INFO     | Device state "connected" or "logged_in"                   | –                                   |
 * | WARNING  | Risk score ≥ warning threshold within the time window     | WhatsAppSessionWarningEvent         |
 * | CRITICAL | Device state "logged_out" or auth/device-not-found error  | WhatsAppAuthenticationErrorEvent    |
 *
 * ### Risk scoring (per entry within the observation window)
 *
 * | Device state      | Points |
 * |-------------------|--------|
 * | disconnected      |     20 |
 * | connecting        |     10 |
 * | unreachable       |     50 |
 * | any other unknown |     15 |
 *
 * Additionally, each state oscillation (healthy → unhealthy transition) adds
 * 10 extra points to capture rapid reconnection cycles indicative of instability.
 *
 * ### Default thresholds (configurable via IAppConfig)
 *
 * | Config key                                  | Default | Meaning                                    |
 * |---------------------------------------------|---------|--------------------------------------------|
 * | gowhatsapp_health_time_window               | 600     | Observation window in seconds (10 minutes) |
 * | gowhatsapp_health_warning_score_threshold   | 80      | Risk score needed to raise WARNING         |
 * | gowhatsapp_health_warning_cooldown          | 3600    | Seconds to suppress repeated WARNINGs      |
 */
class SessionHealthService {
	public const CONFIG_HISTORY_KEY = 'gowhatsapp_health_history';
	public const CONFIG_LAST_WARNING_TS = 'gowhatsapp_health_last_warning_ts';

	private const CONFIG_TIME_WINDOW = 'gowhatsapp_health_time_window';
	private const CONFIG_WARNING_SCORE = 'gowhatsapp_health_warning_score_threshold';
	private const CONFIG_WARNING_COOLDOWN = 'gowhatsapp_health_warning_cooldown';

	private const APPCONFIG_KEY_BASE_URL = 'gowhatsapp_base_url';
	private const APPCONFIG_KEY_DEVICE_ID = 'gowhatsapp_device_id';

	/** States that are considered healthy; everything else contributes to risk */
	private const HEALTHY_STATES = ['connected', 'logged_in'];

	/** States that immediately trigger CRITICAL */
	private const CRITICAL_STATES = ['logged_out'];

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IClientService $clientService,
		private readonly IEventDispatcher $eventDispatcher,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Main entry point called by the background job.
	 *
	 * Fetches device state from the Go service, updates the rolling history,
	 * computes a risk score, and dispatches the appropriate event when needed.
	 */
	public function checkAndDispatch(): void {
		$baseUrl = $this->getBaseUrl();
		if ($baseUrl === '') {
			$this->logger->debug('GoWhatsApp base URL not configured; skipping session health check.');
			return;
		}

		$now = $this->timeFactory->getTime();
		$deviceState = $this->fetchDeviceState($baseUrl);

		if ($this->isCriticalState($deviceState)) {
			$this->logger->warning('GoWhatsApp session health: device is logged_out → dispatching CRITICAL event.');
			$this->eventDispatcher->dispatchTyped(new WhatsAppAuthenticationErrorEvent());
			return;
		}

		$history = $this->updateHistory($deviceState, $now);
		$riskScore = $this->computeRiskScore($history);

		$this->logger->debug('GoWhatsApp session health check completed.', [
			'device_state' => $deviceState,
			'history_entries' => count($history),
			'risk_score' => $riskScore,
		]);

		if ($riskScore < $this->getConfigInt(self::CONFIG_WARNING_SCORE, 80)) {
			return;
		}

		if ($this->isWithinCooldown($now)) {
			return;
		}

		$this->dispatchWarning($riskScore, $history, $now);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private function getBaseUrl(): string {
		return $this->appConfig->getValueString(
			Application::APP_ID,
			self::APPCONFIG_KEY_BASE_URL,
			'',
		);
	}

	private function isCriticalState(string $state): bool {
		return in_array($state, self::CRITICAL_STATES, true);
	}

	/**
	 * Appends the current state to the rolling history, prunes entries outside
	 * the observation window, persists the result, and returns the pruned list.
	 */
	private function updateHistory(string $state, int $now): array {
		$window = $this->getConfigInt(self::CONFIG_TIME_WINDOW, 600);
		$history = $this->loadHistory();
		$history[] = ['ts' => $now, 'state' => $state];
		$history = array_values(array_filter(
			$history,
			static fn (array $e): bool => $e['ts'] >= $now - $window,
		));
		$this->saveHistory($history);
		return $history;
	}

	/**
	 * Returns true (and logs a debug message) when a WARNING was already sent
	 * within the configured cooldown period, suppressing duplicate alerts.
	 */
	private function isWithinCooldown(int $now): bool {
		$cooldown = $this->getConfigInt(self::CONFIG_WARNING_COOLDOWN, 3600);
		$lastWarnTs = (int)$this->appConfig->getValueString(
			Application::APP_ID,
			self::CONFIG_LAST_WARNING_TS,
			'0',
		);
		$elapsed = $now - $lastWarnTs;
		if ($elapsed < $cooldown) {
			$this->logger->debug('GoWhatsApp session WARNING suppressed (within cooldown).', [
				'seconds_since_last_warning' => $elapsed,
			]);
			return true;
		}
		return false;
	}

	/**
	 * Records the warning timestamp, logs it, and dispatches the WARNING event.
	 */
	private function dispatchWarning(int $riskScore, array $history, int $now): void {
		$reason = $this->buildWarningReason($history, $riskScore);
		$this->logger->warning('GoWhatsApp session health: instability detected → dispatching WARNING event.', [
			'risk_score' => $riskScore,
			'reason' => $reason,
		]);
		$this->appConfig->setValueString(
			Application::APP_ID,
			self::CONFIG_LAST_WARNING_TS,
			(string)$now,
		);
		$this->eventDispatcher->dispatchTyped(new WhatsAppSessionWarningEvent($riskScore, $reason));
	}

	/** Reads an integer config value, falling back to $default when absent. */
	private function getConfigInt(string $key, int $default): int {
		return (int)$this->appConfig->getValueString(
			Application::APP_ID,
			$key,
			(string)$default,
		);
	}

	/**
	 * Fetches the current device state from the Go API.
	 *
	 * Returns a state string: one of the values found in the `/devices` response,
	 * or "unreachable" when the API cannot be contacted.
	 */
	private function fetchDeviceState(string $baseUrl): string {
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

			$devices = $data['results'];

			// If a specific device ID is configured, find the matching entry
			if ($deviceId !== '') {
				foreach ($devices as $device) {
					if (($device['id'] ?? '') === $deviceId) {
						return (string)($device['state'] ?? 'disconnected');
					}
				}
				// Configured device ID not found in list
				$this->logger->warning('GoWhatsApp device_id not found in /devices response.', [
					'device_id' => $deviceId,
				]);
				return 'logged_out';
			}

			// No specific device configured – use the first one
			if (!empty($devices)) {
				return (string)($devices[0]['state'] ?? 'disconnected');
			}

			return 'disconnected';
		} catch (\JsonException $e) {
			$this->logger->error('GoWhatsApp /devices response is not valid JSON.', ['exception' => $e]);
			return 'unreachable';
		} catch (\Exception $e) {
			$this->logger->info('GoWhatsApp API unreachable during health check.', ['exception' => $e]);
			return 'unreachable';
		}
	}

	/**
	 * Computes an integer risk score for the given history entries.
	 *
	 * Healthy states contribute 0 points.  All other states contribute a
	 * positive weight, and each oscillation (healthy → unhealthy) adds a
	 * bonus to capture rapid reconnection cycles.
	 */
	private function computeRiskScore(array $history): int {
		$score = 0;
		$prevHealthy = true; // assume healthy before first recorded entry

		foreach ($history as $entry) {
			$state = $entry['state'] ?? 'unknown';
			$isHealthy = in_array($state, self::HEALTHY_STATES, true);

			if (!$isHealthy) {
				$score += match ($state) {
					'disconnected' => 20,
					'connecting' => 10,
					'unreachable' => 50,
					default => 15,
				};
				// Extra penalty for oscillation (was healthy, now not)
				if ($prevHealthy) {
					$score += 10;
				}
			}

			$prevHealthy = $isHealthy;
		}

		return $score;
	}

	/**
	 * Builds a human-readable warning reason string summarising the observed
	 * unhealthy states in the current history window.
	 */
	private function buildWarningReason(array $history, int $riskScore): string {
		$counts = [];
		foreach ($history as $entry) {
			$state = $entry['state'] ?? 'unknown';
			if (!in_array($state, self::HEALTHY_STATES, true)) {
				$counts[$state] = ($counts[$state] ?? 0) + 1;
			}
		}

		$parts = [];
		foreach ($counts as $state => $count) {
			$parts[] = "$count × $state";
		}

		$summary = implode(', ', $parts);
		return "Session instability detected (risk score: $riskScore). Observed: $summary. "
			. 'The session may require re-authentication soon.';
	}

	private function loadHistory(): array {
		$raw = $this->appConfig->getValueString(
			Application::APP_ID,
			self::CONFIG_HISTORY_KEY,
			'[]',
		);
		try {
			$decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
			return is_array($decoded) ? $decoded : [];
		} catch (\JsonException) {
			return [];
		}
	}

	private function saveHistory(array $history): void {
		$this->appConfig->setValueString(
			Application::APP_ID,
			self::CONFIG_HISTORY_KEY,
			json_encode($history, JSON_THROW_ON_ERROR),
		);
	}
}
