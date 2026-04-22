<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Signal;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCP\IAppConfig;

class InteractiveSetupStateStore {
	private const SETUP_STATE_PREFIX = 'signal_setup_state:';

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly int $ttlSeconds = 1800,
	) {
	}

	public function createSessionId(): string {
		return bin2hex(random_bytes(16));
	}

	/** @param array<string, mixed> $state */
	public function save(string $sessionId, array $state): void {
		$state['expires_at'] = time() + $this->ttlSeconds;

		$this->appConfig->setValueString(
			Application::APP_ID,
			$this->buildKey($sessionId),
			json_encode($state, JSON_THROW_ON_ERROR),
		);
	}

	/** @return array<string, mixed>|null */
	public function load(string $sessionId): ?array {
		$key = $this->buildKey($sessionId);
		if (!$this->appConfig->hasKey(Application::APP_ID, $key)) {
			return null;
		}

		$raw = $this->appConfig->getValueString(Application::APP_ID, $key, '');
		if ($raw === '') {
			return null;
		}

		try {
			$state = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			$this->delete($sessionId);
			return null;
		}

		if (!is_array($state)) {
			$this->delete($sessionId);
			return null;
		}

		$expiresAt = (int)($state['expires_at'] ?? 0);
		if ($expiresAt > 0 && $expiresAt <= time()) {
			$this->delete($sessionId);
			return null;
		}

		return $state;
	}

	public function delete(string $sessionId): void {
		$this->appConfig->deleteKey(Application::APP_ID, $this->buildKey($sessionId));
	}

	private function buildKey(string $sessionId): string {
		return self::SETUP_STATE_PREFIX . $sessionId;
	}
}
