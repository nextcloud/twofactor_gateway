<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp;

/**
 * Pure, stateless scorer for GoWhatsApp session health history.
 *
 * Has no dependencies — it operates entirely on plain array data.
 * Each history entry must have the shape: {ts: int, state: string}.
 *
 * ### State weights (per unhealthy entry in the observation window)
 *
 * | State         | Points |
 * |---------------|--------|
 * | disconnected  |     20 |
 * | connecting    |     10 |
 * | unreachable   |     50 |
 * | any unknown   |     15 |
 *
 * An additional +10 oscillation penalty is added on each healthy → unhealthy
 * transition, capturing rapid reconnection cycles indicative of instability.
 */
class HealthRiskScorer {
	/** @var string[] States considered healthy; all others contribute to risk */
	public const HEALTHY_STATES = ['connected', 'logged_in'];

	/**
	 * Points assigned per unhealthy state observation.
	 * States absent from the map fall back to DEFAULT_STATE_WEIGHT.
	 *
	 * @var array<string, int>
	 */
	private const STATE_WEIGHTS = [
		'disconnected' => 20,
		'connecting' => 10,
		'unreachable' => 50,
	];

	private const DEFAULT_STATE_WEIGHT = 15;
	private const OSCILLATION_PENALTY = 10;

	/**
	 * Computes a risk score from the history window.
	 *
	 * Healthy entries contribute 0 points. Unhealthy entries add their state
	 * weight, plus OSCILLATION_PENALTY when the previous entry was healthy.
	 *
	 * @param array<array{ts: int, state: string}> $history
	 */
	public function computeScore(array $history): int {
		$score = 0;
		$prevHealthy = true; // assume healthy before the first recorded entry

		foreach ($history as $entry) {
			$state = $entry['state'] ?? 'unknown';
			$isHealthy = in_array($state, self::HEALTHY_STATES, true);

			if (!$isHealthy) {
				$score += self::STATE_WEIGHTS[$state] ?? self::DEFAULT_STATE_WEIGHT;
				if ($prevHealthy) {
					$score += self::OSCILLATION_PENALTY;
				}
			}

			$prevHealthy = $isHealthy;
		}

		return $score;
	}

	/**
	 * Builds a human-readable warning reason summarising the unhealthy states
	 * observed in the current history window.
	 *
	 * @param array<array{ts: int, state: string}> $history
	 */
	public function buildReason(array $history, int $score): string {
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
		return "Session instability detected (risk score: $score). Observed: $summary. "
			. 'The session may require re-authentication soon.';
	}
}
