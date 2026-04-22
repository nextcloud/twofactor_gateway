<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\BackgroundJob;

use OCA\TwoFactorGateway\Provider\Channel\Telegram\Service\TelegramClientSessionMonitorJobManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Periodically reconciles whether the Telegram Client monitor job should exist.
 */
class TelegramClientSessionMonitorReconcileJob extends TimedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private readonly TelegramClientSessionMonitorJobManager $jobManager,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(900);
	}

	#[\Override]
	protected function run(mixed $argument): void {
		$this->jobManager->sync();
	}
}
