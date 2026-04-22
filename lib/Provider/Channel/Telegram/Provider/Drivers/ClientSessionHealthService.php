<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Events\TelegramAuthenticationErrorEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IAppData;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * Performs a lightweight health check on the active Telegram Client session.
 *
 * The check reuses the existing client call that performs a real API query
 * (`fullGetSelf`) through `fetchLoggedInAccountInfo()`. Empty payload means the
 * session is no longer authenticated and should trigger an admin notification.
 */
class ClientSessionHealthService {
	private const CONFIG_LAST_ERROR_TS = 'telegram_client_health_last_error_ts';
	private const CONFIG_WARNING_COOLDOWN = 'telegram_client_health_warning_cooldown';

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IEventDispatcher $eventDispatcher,
		private readonly IL10N $l10n,
		private readonly IAppData $appData,
		private readonly IConfig $config,
		private readonly LoggerInterface $logger,
	) {
	}

	public function isTelegramClientConfigured(): bool {
		return $this->resolveActiveTelegramClientRuntimeConfig() !== null;
	}

	public function checkAndDispatch(): void {
		$runtimeConfig = $this->resolveActiveTelegramClientRuntimeConfig();
		if ($runtimeConfig === null) {
			$this->logger->debug('Telegram Client monitor skipped: active gateway is not telegram_client or is incomplete.');
			return;
		}

		$provider = new Client(
			logger: $this->logger,
			l10n: $this->l10n,
			appData: $this->appData,
			config: $this->config,
		);

		try {
			$accountInfo = $provider->withRuntimeConfig($runtimeConfig)->fetchLoggedInAccountInfo();
		} catch (\Throwable $e) {
			$this->logger->warning('Telegram Client health probe failed while reading account info.', [
				'exception' => $e,
			]);
			$accountInfo = [];
		}
		if ($accountInfo !== []) {
			$this->appConfig->setValueString(Application::APP_ID, self::CONFIG_LAST_ERROR_TS, '0');
			return;
		}

		$now = time();
		$cooldown = $this->getConfigInt(self::CONFIG_WARNING_COOLDOWN, 3600);
		$lastErrorTs = $this->getConfigInt(self::CONFIG_LAST_ERROR_TS, 0);
		if ($lastErrorTs > 0 && ($now - $lastErrorTs) < $cooldown) {
			$this->logger->debug('Telegram Client auth error notification suppressed (within cooldown).', [
				'seconds_since_last_warning' => $now - $lastErrorTs,
			]);
			return;
		}

		$this->logger->warning('Telegram Client session appears unauthenticated; dispatching auth error event.');
		$this->appConfig->setValueString(Application::APP_ID, self::CONFIG_LAST_ERROR_TS, (string)$now);
		$this->eventDispatcher->dispatchTyped(new TelegramAuthenticationErrorEvent());
	}

	/** @return array<string, string>|null */
	private function resolveActiveTelegramClientRuntimeConfig(): ?array {
		$providerName = trim($this->appConfig->getValueString(Application::APP_ID, 'telegram_provider_name', ''));
		if ($providerName !== '') {
			if ($providerName !== 'telegram_client') {
				return null;
			}

			$apiId = trim($this->appConfig->getValueString(Application::APP_ID, 'telegram_client_api_id', ''));
			$apiHash = trim($this->appConfig->getValueString(Application::APP_ID, 'telegram_client_api_hash', ''));
			if ($apiId === '' || $apiHash === '') {
				return null;
			}

			return [
				'provider' => 'telegram_client',
				'api_id' => $apiId,
				'api_hash' => $apiHash,
			];
		}

		$registryRaw = $this->appConfig->getValueString(Application::APP_ID, 'instances:telegram', '[]');
		$registry = json_decode($registryRaw, true);
		if (!is_array($registry)) {
			return null;
		}

		$defaultInstanceId = '';
		foreach ($registry as $instanceMeta) {
			if (!is_array($instanceMeta) || !($instanceMeta['default'] ?? false)) {
				continue;
			}

			$defaultInstanceId = trim((string)($instanceMeta['id'] ?? ''));
			if ($defaultInstanceId !== '') {
				break;
			}
		}

		if ($defaultInstanceId === '') {
			return null;
		}

		$provider = trim($this->appConfig->getValueString(
			Application::APP_ID,
			'telegram:' . $defaultInstanceId . ':provider',
			'',
		));
		if ($provider !== 'telegram_client') {
			return null;
		}

		$apiId = trim($this->appConfig->getValueString(
			Application::APP_ID,
			'telegram:' . $defaultInstanceId . ':api_id',
			'',
		));
		$apiHash = trim($this->appConfig->getValueString(
			Application::APP_ID,
			'telegram:' . $defaultInstanceId . ':api_hash',
			'',
		));
		if ($apiId === '' || $apiHash === '') {
			return null;
		}

		return [
			'provider' => 'telegram_client',
			'api_id' => $apiId,
			'api_hash' => $apiHash,
		];
	}

	private function getConfigInt(string $key, int $default): int {
		return (int)$this->appConfig->getValueString(
			Application::APP_ID,
			$key,
			(string)$default,
		);
	}
}
