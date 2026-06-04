<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\GatewayPermissionDeniedException;
use OCP\IAppConfig;
use OCP\IUser;

class GatewayInteractiveSetupSessionService {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function claim(?IUser $actor, string $gatewayId, string $sessionId): void {
		$ownerUserId = $actor?->getUID();
		$sessionId = trim($sessionId);
		if ($ownerUserId === null || $sessionId === '') {
			return;
		}

		$this->appConfig->setValueString(
			Application::APP_ID,
			$this->buildKey($gatewayId, $sessionId),
			json_encode(['ownerUserId' => $ownerUserId], JSON_THROW_ON_ERROR),
		);
	}

	/** @throws GatewayPermissionDeniedException */
	public function assertCanAccess(?IUser $actor, string $gatewayId, string $sessionId): void {
		$ownership = $this->loadOwnership($gatewayId, $sessionId);
		if ($ownership === null) {
			return;
		}

		$ownerUserId = trim((string)($ownership['ownerUserId'] ?? ''));
		if ($ownerUserId === '' || $actor?->getUID() === $ownerUserId) {
			return;
		}

		throw new GatewayPermissionDeniedException('You are not allowed to access this interactive setup session.');
	}

	public function release(string $gatewayId, string $sessionId): void {
		$sessionId = trim($sessionId);
		if ($sessionId === '') {
			return;
		}

		$this->appConfig->deleteKey(Application::APP_ID, $this->buildKey($gatewayId, $sessionId));
	}

	/** @return array<string, mixed>|null */
	private function loadOwnership(string $gatewayId, string $sessionId): ?array {
		$key = $this->buildKey($gatewayId, $sessionId);
		if (!$this->appConfig->hasKey(Application::APP_ID, $key)) {
			return null;
		}

		$raw = $this->appConfig->getValueString(Application::APP_ID, $key, '');
		if ($raw === '') {
			return null;
		}

		try {
			$decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			$this->appConfig->deleteKey(Application::APP_ID, $key);
			return null;
		}

		return is_array($decoded) ? $decoded : null;
	}

	private function buildKey(string $gatewayId, string $sessionId): string {
		return 'interactive_setup_session_owner:' . $gatewayId . ':' . $sessionId;
	}
}
