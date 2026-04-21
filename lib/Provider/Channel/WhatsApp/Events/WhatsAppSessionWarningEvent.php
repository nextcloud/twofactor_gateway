<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Events;

use OCP\EventDispatcher\Event;

/**
 * Dispatched when the GoWhatsApp session health monitor detects instability
 * that may precede a session loss (WARNING level).
 *
 * Instances of this event carry a risk score and a human-readable reason
 * that describe why the warning was raised, so listeners can include that
 * context in notifications.
 */
class WhatsAppSessionWarningEvent extends Event {
	/**
	 * @param int $riskScore Accumulated heuristic risk score that triggered the warning.
	 * @param string $reason Human-readable description of the anomaly detected.
	 */
	public function __construct(
		private readonly int $riskScore,
		private readonly string $reason,
	) {
		parent::__construct();
	}

	public function getRiskScore(): int {
		return $this->riskScore;
	}

	public function getReason(): string {
		return $this->reason;
	}
}
