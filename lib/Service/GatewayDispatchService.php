<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCA\TwoFactorGateway\Exception\GatewayInstanceNotFoundException;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Gateway\ITestResultEnricher;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class GatewayDispatchService {
	public function __construct(
		private GatewayRoutingService $gatewayRoutingService,
		private GatewayRuntimeAvailabilityService $gatewayRuntimeAvailabilityService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Send a message using a specific provider instance. This is the stable path
	 * other apps should use when they already know the provider and instance.
	 *
	 * @param array<string, mixed> $extra
	 * @return array{providerId: string, instanceId: string, publicInstanceId: string, label: string}
	 *
	 * @throws GatewayInstanceNotFoundException
	 * @throws MessageTransmissionException
	 */
	public function sendWithInstance(string $providerId, string $instanceId, string $identifier, string $message, array $extra = []): array {
		$candidate = $this->gatewayRoutingService->resolveProviderInstance($providerId, $instanceId);
		return $this->dispatchCandidate($candidate, $identifier, $message, $extra);
	}

	/**
	 * Send a message using the instance reference exposed by the admin API.
	 * For catalog gateways this accepts the prefixed reference like gowhatsapp:abc123.
	 *
	 * @param array<string, mixed> $extra
	 * @return array{providerId: string, instanceId: string, publicInstanceId: string, label: string}
	 *
	 * @throws GatewayInstanceNotFoundException
	 * @throws MessageTransmissionException
	 */
	public function sendWithReference(string $gatewayId, string $instanceId, string $identifier, string $message, array $extra = []): array {
		$candidate = $this->gatewayRoutingService->resolveGatewayInstanceReference($gatewayId, $instanceId);
		return $this->dispatchCandidate($candidate, $identifier, $message, $extra);
	}

	/**
	 * Resolve routing for the given user using per-instance group assignments and
	 * fallback rules, then send through the first working instance.
	 *
	 * @param array<string, mixed> $extra
	 * @return array{providerId: string, instanceId: string, publicInstanceId: string, label: string}
	 *
	 * @throws MessageTransmissionException
	 */
	public function sendForUser(IUser $user, string $gatewayId, string $identifier, string $message, array $extra = []): array {
		$candidates = $this->gatewayRuntimeAvailabilityService->resolveCandidatesForUser($user, $gatewayId);

		if ($candidates === []) {
			$gateway = $this->gatewayRuntimeAvailabilityService->getGateway($gatewayId);
			$gateway->send($identifier, $message, $extra);
			return [
				'providerId' => $gateway->getProviderId(),
				'instanceId' => '',
				'publicInstanceId' => '',
				'label' => '',
			];
		}

		$lastError = null;
		foreach ($candidates as $candidate) {
			try {
				return $this->dispatchCandidate($candidate, $identifier, $message, $extra);
			} catch (MessageTransmissionException $e) {
				$lastError = $e;
				$this->logger->warning('Gateway instance dispatch failed, trying next fallback candidate.', [
					'gatewayId' => $gatewayId,
					'providerId' => $candidate->providerId,
					'instanceId' => $candidate->instance->id,
					'publicInstanceId' => $candidate->publicInstanceId,
					'exception' => $e,
				]);
			}
		}

		throw $lastError ?? new MessageTransmissionException('No available configured gateway instance could send the message.');
	}

	/**
	 * @return array<string, string>
	 *
	 * @throws GatewayInstanceNotFoundException
	 */
	public function enrichTestResultForReference(string $gatewayId, string $instanceId, string $identifier = ''): array {
		$candidate = $this->gatewayRoutingService->resolveGatewayInstanceReference($gatewayId, $instanceId);
		$gateway = $candidate->gateway;
		if (!($gateway instanceof ITestResultEnricher)) {
			return [];
		}

		return $gateway->enrichTestResult($candidate->instance->config, $identifier);
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array{providerId: string, instanceId: string, publicInstanceId: string, label: string}
	 *
	 * @throws MessageTransmissionException
	 */
	private function dispatchCandidate(GatewayRouteCandidate $candidate, string $identifier, string $message, array $extra = []): array {
		$instance = $candidate->instance;
		if (!$instance->isComplete) {
			throw new MessageTransmissionException('Gateway instance is not fully configured.');
		}

		$gateway = $candidate->gateway;
		if ($gateway instanceof AGateway) {
			$gateway = $gateway->withRuntimeConfig($instance->config);
		}

		$gateway->send($identifier, $message, $extra);

		return [
			'providerId' => $candidate->providerId,
			'instanceId' => $instance->id,
			'publicInstanceId' => $candidate->publicInstanceId,
			'label' => $instance->label,
		];
	}
}
