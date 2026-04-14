<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\GoWhatsApp;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Receives signed GoWhatsApp webhook payloads and triggers a health check.
 *
 * Polling remains active as fallback; this only shortens detection latency.
 */
class WebhookIngestionService {
	private const APP_ID = 'twofactor_gateway';

	private const APPCONFIG_KEY_WEBHOOK_ENABLED = 'gowhatsapp_webhook_hybrid_enabled';
	private const APPCONFIG_KEY_WEBHOOK_SECRET = 'gowhatsapp_webhook_secret';
	private const APPCONFIG_KEY_DEVICE_ID = 'gowhatsapp_device_id';
	private const APPCONFIG_KEY_MIN_CHECK_INTERVAL = 'gowhatsapp_webhook_min_check_interval';

	private const APPCONFIG_KEY_LAST_EVENT_HASH = 'gowhatsapp_webhook_last_event_hash';
	private const APPCONFIG_KEY_LAST_EVENT_TS = 'gowhatsapp_webhook_last_event_ts';
	private const APPCONFIG_KEY_LAST_CHECK_TS = 'gowhatsapp_webhook_last_check_ts';

	private const DEFAULT_MIN_CHECK_INTERVAL = 30;
	private const DUPLICATE_WINDOW = 120;

	public function __construct(
		private IAppConfig $appConfig,
		private SessionHealthService $sessionHealthService,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return array{status: int, processed: bool, message: string}
	 */
	public function ingest(string $rawBody, string $signatureHeader): array {
		if (!$this->isHybridEnabled()) {
			return [
				'status' => 202,
				'processed' => false,
				'message' => 'hybrid webhook is disabled',
			];
		}

		$secret = trim($this->appConfig->getValueString(self::APP_ID, self::APPCONFIG_KEY_WEBHOOK_SECRET, ''));
		if ($secret === '') {
			$this->logger->warning('GoWhatsApp webhook request rejected: no webhook secret configured.');
			return [
				'status' => 503,
				'processed' => false,
				'message' => 'webhook secret is not configured',
			];
		}

		if (!$this->hasValidSignature($rawBody, $signatureHeader, $secret)) {
			$this->logger->warning('GoWhatsApp webhook request rejected: invalid signature.');
			return [
				'status' => 401,
				'processed' => false,
				'message' => 'invalid webhook signature',
			];
		}

		try {
			$data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			$this->logger->warning('GoWhatsApp webhook request rejected: invalid JSON payload.', [
				'exception' => $e,
			]);
			return [
				'status' => 400,
				'processed' => false,
				'message' => 'invalid JSON payload',
			];
		}

		$configuredDeviceId = trim($this->appConfig->getValueString(self::APP_ID, self::APPCONFIG_KEY_DEVICE_ID, ''));
		$payloadDeviceId = is_string($data['device_id'] ?? null) ? trim($data['device_id']) : '';
		if ($configuredDeviceId !== '' && $payloadDeviceId !== '' && $configuredDeviceId !== $payloadDeviceId) {
			return [
				'status' => 202,
				'processed' => false,
				'message' => 'webhook ignored for different device_id',
			];
		}

		$now = $this->timeFactory->getTime();
		$eventHash = hash('sha256', $rawBody);
		if ($this->isDuplicateEvent($eventHash, $now)) {
			return [
				'status' => 202,
				'processed' => false,
				'message' => 'duplicate webhook ignored',
			];
		}

		$this->markEventAsSeen($eventHash, $now);

		$lastCheckTs = (int)$this->appConfig->getValueString(self::APP_ID, self::APPCONFIG_KEY_LAST_CHECK_TS, '0');
		$minInterval = max(0, (int)$this->appConfig->getValueString(
			self::APP_ID,
			self::APPCONFIG_KEY_MIN_CHECK_INTERVAL,
			(string)self::DEFAULT_MIN_CHECK_INTERVAL,
		));

		if ($lastCheckTs > 0 && ($now - $lastCheckTs) < $minInterval) {
			return [
				'status' => 202,
				'processed' => false,
				'message' => 'health check rate limited',
			];
		}

		$this->sessionHealthService->checkAndDispatch();
		$this->appConfig->setValueString(self::APP_ID, self::APPCONFIG_KEY_LAST_CHECK_TS, (string)$now);

		return [
			'status' => 202,
			'processed' => true,
			'message' => 'webhook accepted and health check executed',
		];
	}

	private function isHybridEnabled(): bool {
		$raw = strtolower(trim($this->appConfig->getValueString(self::APP_ID, self::APPCONFIG_KEY_WEBHOOK_ENABLED, '0')));
		return in_array($raw, ['1', 'true', 'yes', 'on'], true);
	}

	private function hasValidSignature(string $rawBody, string $signatureHeader, string $secret): bool {
		$signatureHeader = trim($signatureHeader);
		if (!str_starts_with($signatureHeader, 'sha256=')) {
			return false;
		}

		$providedDigest = substr($signatureHeader, 7);
		if ($providedDigest === '' || !ctype_xdigit($providedDigest)) {
			return false;
		}

		$expectedDigest = hash_hmac('sha256', $rawBody, $secret);
		return hash_equals($expectedDigest, strtolower($providedDigest));
	}

	private function isDuplicateEvent(string $eventHash, int $now): bool {
		$lastEventHash = $this->appConfig->getValueString(self::APP_ID, self::APPCONFIG_KEY_LAST_EVENT_HASH, '');
		$lastEventTs = (int)$this->appConfig->getValueString(self::APP_ID, self::APPCONFIG_KEY_LAST_EVENT_TS, '0');

		return $lastEventHash === $eventHash
			&& $lastEventTs > 0
			&& ($now - $lastEventTs) <= self::DUPLICATE_WINDOW;
	}

	private function markEventAsSeen(string $eventHash, int $now): void {
		$this->appConfig->setValueString(self::APP_ID, self::APPCONFIG_KEY_LAST_EVENT_HASH, $eventHash);
		$this->appConfig->setValueString(self::APP_ID, self::APPCONFIG_KEY_LAST_EVENT_TS, (string)$now);
	}
}
