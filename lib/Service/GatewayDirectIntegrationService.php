<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;

/**
 * Stable direct-integration path for other apps that only know the gateway id
 * and destination identifier, and do not need user routing or instance
 * selection details.
 */
class GatewayDirectIntegrationService {
	public function __construct(
		private GatewayRuntimeAvailabilityService $gatewayRuntimeAvailabilityService,
	) {
	}

	public function ensureAvailable(string $gatewayId): void {
		$this->gatewayRuntimeAvailabilityService->getGateway($gatewayId);
	}

	public function isGatewayComplete(string $gatewayId): bool {
		return $this->gatewayRuntimeAvailabilityService->hasDirectGatewayFallback($gatewayId);
	}

	/**
	 * @param array<string, mixed> $extra
	 * @throws MessageTransmissionException
	 */
	public function send(string $gatewayId, string $identifier, string $message, array $extra = []): void {
		$this->gatewayRuntimeAvailabilityService->getGateway($gatewayId)->send($identifier, $message, $extra);
	}
}
