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
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates the GoWhatsApp session health check cycle.
 *
 * On each invocation by the background job it:
 *   1. Guards against an unconfigured base URL.
 *   2. Delegates HTTP fetching to DeviceStateFetcher.
 *   3. Short-circuits on CRITICAL states (dispatches WhatsAppAuthenticationErrorEvent).
 *   4. Updates the rolling history window stored in IAppConfig.
 *   5. Delegates risk scoring to HealthRiskScorer.
 *   6. Suppresses WARNING repeats within the cooldown period.
 *   7. Dispatches WhatsAppSessionWarningEvent when the risk score exceeds the threshold.
 *
 * ### Default thresholds (configurable via IAppConfig)
 *
 * | Config key                                  | Default | Meaning                                    |
 * |---------------------------------------------|---------|--------------------------------------------|
 * | gowhatsapp_health_time_window               | 600     | Observation window in seconds (10 minutes) |
 * | gowhatsapp_health_warning_score_threshold   | 80      | Risk score needed to raise WARNING         |
 * | gowhatsapp_health_warning_cooldown          | 3600    | Seconds to suppress repeated WARNINGs      |
 *
 * See HealthRiskScorer for state weights and scoring details.
 */
class SessionHealthService {
	public const CONFIG_HISTORY_KEY = 'gowhatsapp_health_history';
	public const CONFIG_LAST_WARNING_TS = 'gowhatsapp_health_last_warning_ts';

	private const CONFIG_TIME_WINDOW = 'gowhatsapp_health_time_window';
	private const CONFIG_WARNING_SCORE = 'gowhatsapp_health_warning_score_threshold';
	private const CONFIG_WARNING_COOLDOWN = 'gowhatsapp_health_warning_cooldown';

	private const APPCONFIG_KEY_BASE_URL = 'gowhatsapp_base_url';

	/** States that immediately trigger CRITICAL */
	private const CRITICAL_STATES = ['logged_out'];

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly DeviceStateFetcher $deviceStateFetcher,
		private readonly HealthRiskScorer $riskScorer,
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
		$deviceState = $this->deviceStateFetcher->fetch($baseUrl);

		if ($this->isCriticalState($deviceState)) {
			$this->logger->warning('GoWhatsApp session health: device is logged_out → dispatching CRITICAL event.');
			$this->eventDispatcher->dispatchTyped(new WhatsAppAuthenticationErrorEvent());
			return;
		}

		$history = $this->updateHistory($deviceState, $now);
		$riskScore = $this->riskScorer->computeScore($history);

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
		$reason = $this->riskScorer->buildReason($history, $riskScore);
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
