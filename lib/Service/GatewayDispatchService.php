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
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway;
use OCA\TwoFactorGateway\Provider\Gateway\ITestResultEnricher;
use OCP\IGroupManager;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class GatewayDispatchService {
	public function __construct(
		private GatewayFactory $gatewayFactory,
		private GatewayConfigService $gatewayConfigService,
		private IGroupManager $groupManager,
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
		$candidate = $this->resolveProviderInstance($providerId, $instanceId);
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
		$candidate = $this->resolveGatewayInstanceReference($gatewayId, $instanceId);
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
		$candidates = $this->resolveUserCandidates($user, $gatewayId);

		if ($candidates === []) {
			$gateway = $this->gatewayFactory->get($gatewayId);
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
					'providerId' => $candidate['providerId'],
					'instanceId' => $candidate['instance']['id'],
					'publicInstanceId' => $candidate['publicInstanceId'],
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
		$candidate = $this->resolveGatewayInstanceReference($gatewayId, $instanceId);
		$gateway = $candidate['gateway'];
		if (!($gateway instanceof ITestResultEnricher)) {
			return [];
		}

		return $gateway->enrichTestResult($candidate['instance']['config'], $identifier);
	}

	/**
	 * @return array{gateway: IGateway, providerId: string, publicInstanceId: string, instance: array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}}
	 * @throws GatewayInstanceNotFoundException
	 */
	private function resolveProviderInstance(string $providerId, string $instanceId): array {
		$gateway = $this->gatewayFactory->get($providerId);
		$instance = $this->gatewayConfigService->getInstance($gateway, $instanceId);
		return [
			'gateway' => $gateway,
			'providerId' => $gateway->getProviderId(),
			'publicInstanceId' => $instance['id'],
			'instance' => $instance,
		];
	}

	/**
	 * @return array{gateway: IGateway, providerId: string, publicInstanceId: string, instance: array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}}
	 * @throws GatewayInstanceNotFoundException
	 */
	private function resolveGatewayInstanceReference(string $gatewayId, string $instanceId): array {
		foreach ($this->listResolvedInstances($gatewayId) as $candidate) {
			if ($candidate['publicInstanceId'] === $instanceId) {
				return $candidate;
			}
		}

		throw new GatewayInstanceNotFoundException($gatewayId, $instanceId);
	}

	/**
	 * @return list<array{gateway: IGateway, providerId: string, publicInstanceId: string, instance: array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}}>
	 */
	private function listResolvedInstances(string $gatewayId): array {
		$gateway = $this->gatewayFactory->get($gatewayId);
		$candidates = $this->buildGatewayCandidates($gateway, false);

		if (!($gateway instanceof IProviderCatalogGateway)) {
			return $candidates;
		}

		foreach ($gateway->getProviderCatalog() as $provider) {
			$providerId = (string)($provider['id'] ?? '');
			if ($providerId === '' || $providerId === $gateway->getProviderId()) {
				continue;
			}

			try {
				$providerGateway = $this->gatewayFactory->get($providerId);
			} catch (\InvalidArgumentException) {
				continue;
			}

			$candidates = [...$candidates, ...$this->buildGatewayCandidates($providerGateway, true)];
		}

		return $candidates;
	}

	/**
	 * @return list<array{gateway: IGateway, providerId: string, publicInstanceId: string, instance: array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}}>
	 */
	private function buildGatewayCandidates(IGateway $gateway, bool $prefixPublicId): array {
		$candidates = [];
		foreach ($this->gatewayConfigService->listInstances($gateway) as $instance) {
			$publicInstanceId = $prefixPublicId
				? $gateway->getProviderId() . ':' . $instance['id']
				: $instance['id'];

			$candidates[] = [
				'gateway' => $gateway,
				'providerId' => $gateway->getProviderId(),
				'publicInstanceId' => $publicInstanceId,
				'instance' => $instance,
			];
		}

		return $candidates;
	}

	/**
	 * @return list<array{gateway: IGateway, providerId: string, publicInstanceId: string, instance: array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}}>
	 */
	private function resolveUserCandidates(IUser $user, string $gatewayId): array {
		$allCandidates = array_values(array_filter(
			$this->listResolvedInstances($gatewayId),
			static fn (array $candidate): bool => $candidate['instance']['isComplete'],
		));

		if ($allCandidates === []) {
			return [];
		}

		$userGroupIds = array_fill_keys($this->groupManager->getUserGroupIds($user), true);
		$groupCandidates = array_values(array_filter(
			$allCandidates,
			static function (array $candidate) use ($userGroupIds): bool {
				foreach ($candidate['instance']['groupIds'] as $groupId) {
					if (isset($userGroupIds[$groupId])) {
						return true;
					}
				}

				return false;
			},
		));

		if ($groupCandidates !== []) {
			usort($groupCandidates, [$this, 'compareGroupCandidates']);
			return $groupCandidates;
		}

		// Only fall back to instances that have no group restriction (open to all users).
		// Instances that list groups but none match this user are not accessible to them.
		$openCandidates = array_values(array_filter(
			$allCandidates,
			static fn (array $candidate): bool => $candidate['instance']['groupIds'] === [],
		));

		if ($openCandidates !== []) {
			usort($openCandidates, [$this, 'compareFallbackCandidates']);
			return $openCandidates;
		}

		throw new MessageTransmissionException('No gateway instance is accessible for this user. Check group assignments in the gateway configuration.');
	}

	private function compareGroupCandidates(array $left, array $right): int {
		$priorityDiff = $left['instance']['priority'] <=> $right['instance']['priority'];
		if ($priorityDiff !== 0) {
			return $priorityDiff;
		}

		$defaultDiff = (int)$right['instance']['default'] <=> (int)$left['instance']['default'];
		if ($defaultDiff !== 0) {
			return $defaultDiff;
		}

		$createdAtDiff = strcmp($left['instance']['createdAt'], $right['instance']['createdAt']);
		if ($createdAtDiff !== 0) {
			return $createdAtDiff;
		}

		return strcmp($left['publicInstanceId'], $right['publicInstanceId']);
	}

	private function compareFallbackCandidates(array $left, array $right): int {
		$defaultDiff = (int)$right['instance']['default'] <=> (int)$left['instance']['default'];
		if ($defaultDiff !== 0) {
			return $defaultDiff;
		}

		$priorityDiff = $left['instance']['priority'] <=> $right['instance']['priority'];
		if ($priorityDiff !== 0) {
			return $priorityDiff;
		}

		$createdAtDiff = strcmp($left['instance']['createdAt'], $right['instance']['createdAt']);
		if ($createdAtDiff !== 0) {
			return $createdAtDiff;
		}

		return strcmp($left['publicInstanceId'], $right['publicInstanceId']);
	}

	/**
	 * @param array{gateway: IGateway, providerId: string, publicInstanceId: string, instance: array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}} $candidate
	 * @param array<string, mixed> $extra
	 * @return array{providerId: string, instanceId: string, publicInstanceId: string, label: string}
	 *
	 * @throws MessageTransmissionException
	 */
	private function dispatchCandidate(array $candidate, string $identifier, string $message, array $extra = []): array {
		$instance = $candidate['instance'];
		if (!$instance['isComplete']) {
			throw new MessageTransmissionException('Gateway instance is not fully configured.');
		}

		$gateway = $candidate['gateway'];
		if ($gateway instanceof AGateway) {
			$gateway = $gateway->withRuntimeConfig($instance['config']);
		}

		$gateway->send($identifier, $message, $extra);

		return [
			'providerId' => $candidate['providerId'],
			'instanceId' => $instance['id'],
			'publicInstanceId' => $candidate['publicInstanceId'],
			'label' => $instance['label'],
		];
	}
}
