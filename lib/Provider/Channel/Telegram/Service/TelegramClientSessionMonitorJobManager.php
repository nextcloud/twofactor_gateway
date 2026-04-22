<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Service;

use OCA\TwoFactorGateway\Provider\Channel\Telegram\BackgroundJob\TelegramClientSessionMonitorJob;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\BackgroundJob\TelegramClientSessionMonitorReconcileJob;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientSessionHealthService;
use OCP\BackgroundJob\IJobList;
use Psr\Log\LoggerInterface;

class TelegramClientSessionMonitorJobManager {
	public function __construct(
		private readonly ClientSessionHealthService $healthService,
		private readonly IJobList $jobList,
		private readonly LoggerInterface $logger,
	) {
	}

	public function sync(): void {
		try {
			$this->ensureReconcileJobRegistered();

			$shouldBeActive = $this->isTelegramClientConfigured();
			$isActive = $this->jobList->has(TelegramClientSessionMonitorJob::class, null);

			if ($shouldBeActive && !$isActive) {
				$this->jobList->add(TelegramClientSessionMonitorJob::class, null);
				$this->logger->info('Activated Telegram Client session monitor background job.');
				return;
			}

			if (!$shouldBeActive && $isActive) {
				$this->jobList->remove(TelegramClientSessionMonitorJob::class, null);
				$this->logger->info('Deactivated Telegram Client session monitor background job (gateway not configured).');
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to sync Telegram Client session monitor background job.', [
				'exception' => $e,
			]);
		}
	}

	public function ensureReconcileJobRegisteredSafely(): void {
		try {
			$this->ensureReconcileJobRegistered();
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to register Telegram Client monitor reconcile background job.', [
				'exception' => $e,
			]);
		}
	}

	private function ensureReconcileJobRegistered(): void {
		if ($this->jobList->has(TelegramClientSessionMonitorReconcileJob::class, null)) {
			return;
		}

		$this->jobList->add(TelegramClientSessionMonitorReconcileJob::class, null);
		$this->logger->info('Registered Telegram Client monitor reconcile background job.');
	}

	private function isTelegramClientConfigured(): bool {
		return $this->healthService->isTelegramClientConfigured();
	}
}
