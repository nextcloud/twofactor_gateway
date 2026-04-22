<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\BackgroundJob;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\Service\GoWhatsAppSessionMonitorJobManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Periodically reconciles whether the GoWhatsApp monitor job should exist.
 *
 * This avoids boot-time sync (which can trigger lazy AppConfig loading on NC34+)
 * while still self-healing the monitor registration if it is ever removed.
 */
class GoWhatsAppSessionMonitorReconcileJob extends TimedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private readonly GoWhatsAppSessionMonitorJobManager $jobManager,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(900);
	}

	#[\Override]
	protected function run(mixed $argument): void {
		$this->jobManager->sync();
	}
}