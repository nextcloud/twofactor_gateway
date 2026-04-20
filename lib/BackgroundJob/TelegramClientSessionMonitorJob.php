<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\BackgroundJob;

use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientSessionHealthService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Periodic background job that validates whether the active Telegram Client
 * session is still authenticated and dispatches events when it is not.
 */
class TelegramClientSessionMonitorJob extends TimedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private readonly ClientSessionHealthService $healthService,
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
