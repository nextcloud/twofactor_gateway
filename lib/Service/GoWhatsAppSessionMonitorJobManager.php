<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCA\TwoFactorGateway\BackgroundJob\GoWhatsAppSessionMonitorJob;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCP\BackgroundJob\IJobList;
use Psr\Log\LoggerInterface;

class GoWhatsAppSessionMonitorJobManager {
	private const GATEWAY_ID = 'gowhatsapp';

	public function __construct(
		private GatewayFactory $gatewayFactory,
		private IJobList $jobList,
		private LoggerInterface $logger,
	) {
	}

	public function sync(): void {
		$shouldBeActive = $this->isGoWhatsAppConfigured();
		$isActive = $this->jobList->has(GoWhatsAppSessionMonitorJob::class, null);

		if ($shouldBeActive && !$isActive) {
			$this->jobList->add(GoWhatsAppSessionMonitorJob::class, null);
			$this->logger->info('Activated GoWhatsApp session monitor background job.');
			return;
		}

		if (!$shouldBeActive && $isActive) {
			$this->jobList->remove(GoWhatsAppSessionMonitorJob::class, null);
			$this->logger->info('Deactivated GoWhatsApp session monitor background job (gateway not configured).');
		}
	}

	private function isGoWhatsAppConfigured(): bool {
		try {
			$gateway = $this->gatewayFactory->get(self::GATEWAY_ID);
			return $gateway->isComplete();
		} catch (\InvalidArgumentException $e) {
			$this->logger->warning('GoWhatsApp gateway not found while syncing session monitor job.', [
				'exception' => $e,
			]);
			return false;
		}
	}
}
