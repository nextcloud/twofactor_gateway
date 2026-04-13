<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\BackgroundJob;

use OCA\TwoFactorGateway\Provider\Channel\GoWhatsApp\SessionHealthService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Periodic background job that delegates to SessionHealthService to perform a
 * heuristic health check on the GoWhatsApp session and dispatch events when
 * instability or session loss is detected.
 *
 * The job runs every 5 minutes by default, which is the shortest interval
 * available for TimedJob in Nextcloud.
 */
class GoWhatsAppSessionMonitorJob extends TimedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private readonly SessionHealthService $healthService,
	) {
		parent::__construct($timeFactory);
		// Run every 5 minutes (300 seconds)
		$this->setInterval(300);
		$this->setTimeSensitivity(self::TIME_SENSITIVE);
	}

	#[\Override]
	protected function run(mixed $argument): void {
		$this->healthService->checkAndDispatch();
	}
}
