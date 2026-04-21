<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Events;

use OCA\TwoFactorGateway\Events\AdminNotifiableEvent;
use OCP\EventDispatcher\Event;

/**
 * Dispatched when the GoWhatsApp session health monitor detects instability
 * that may precede a session loss (WARNING level).
 *
 * Instances of this event carry a risk score and a human-readable reason
 * that describe why the warning was raised, so listeners can include that
 * context in notifications.
 */
class WhatsAppSessionWarningEvent extends Event implements AdminNotifiableEvent {
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

	#[\Override]
	public function getNotificationSubject(): string {
		return 'whatsapp_session_warning';
	}

	#[\Override]
	public function getNotificationObjectType(): string {
		return 'whatsapp_error';
	}

	#[\Override]
	public function getNotificationObjectId(): string {
		return 'session_health';
	}

	/**
	 * @return array<string, string>
	 */
	#[\Override]
	public function getNotificationParameters(): array {
		return [
			'risk_score' => (string)$this->getRiskScore(),
			'reason' => $this->getReason(),
		];
	}
}
