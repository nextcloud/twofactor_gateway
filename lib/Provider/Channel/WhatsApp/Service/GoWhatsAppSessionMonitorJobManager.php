<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Service;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\BackgroundJob\GoWhatsAppSessionMonitorJob;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\BackgroundJob\GoWhatsAppSessionMonitorReconcileJob;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\ReconfigurationState;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class GoWhatsAppSessionMonitorJobManager {
	private const GATEWAY_ID = 'gowhatsapp';

	public function __construct(
		private GatewayFactory $gatewayFactory,
		private IAppConfig $appConfig,
		private IJobList $jobList,
		private LoggerInterface $logger,
	) {
	}

	public function sync(): void {
		try {
			$this->ensureReconcileJobRegistered();

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
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to sync GoWhatsApp session monitor background job.', [
				'exception' => $e,
			]);
		}
	}

	public function ensureReconcileJobRegisteredSafely(): void {
		try {
			$this->ensureReconcileJobRegistered();
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to register GoWhatsApp monitor reconcile background job.', [
				'exception' => $e,
			]);
		}
	}

	private function ensureReconcileJobRegistered(): void {
		if ($this->jobList->has(GoWhatsAppSessionMonitorReconcileJob::class, null)) {
			return;
		}

		$this->jobList->add(GoWhatsAppSessionMonitorReconcileJob::class, null);
		$this->logger->info('Registered GoWhatsApp monitor reconcile background job.');
	}

	private function isGoWhatsAppConfigured(): bool {
		if (ReconfigurationState::isRequired($this->appConfig)) {
			return false;
		}

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
