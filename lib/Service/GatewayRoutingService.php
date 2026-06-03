<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCA\TwoFactorGateway\Exception\GatewayInstanceNotFoundException;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway;
use OCP\IGroupManager;
use OCP\IUser;

class GatewayRoutingService {
	public function __construct(
		private GatewayFactory $gatewayFactory,
		private GatewayConfigService $gatewayConfigService,
		private IGroupManager $groupManager,
	) {
	}

	public function getGateway(string $gatewayId): IGateway {
		return $this->gatewayFactory->get($gatewayId);
	}

	/**
	 * @throws GatewayInstanceNotFoundException
	 */
	public function resolveProviderInstance(string $providerId, string $instanceId): GatewayRouteCandidate {
		$gateway = $this->gatewayFactory->get($providerId);
		$instance = $this->gatewayConfigService->getInstance($gateway, $instanceId);
		return GatewayRouteCandidate::fromGatewayAndInstance($gateway, $instance, false);
	}

	/**
	 * @throws GatewayInstanceNotFoundException
	 */
	public function resolveGatewayInstanceReference(string $gatewayId, string $instanceId): GatewayRouteCandidate {
		foreach ($this->listResolvedInstances($gatewayId) as $candidate) {
			if ($candidate->publicInstanceId === $instanceId) {
				return $candidate;
			}
		}

		throw new GatewayInstanceNotFoundException($gatewayId, $instanceId);
	}

	/**
	 * @return list<GatewayRouteCandidate>
	 */
	public function listResolvedInstances(string $gatewayId): array {
		$gateway = $this->getGateway($gatewayId);
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

			$candidates = [
				...$candidates,
				...$this->buildGatewayCandidates($providerGateway, true),
			];
		}

		return $candidates;
	}

	/**
	 * @return list<GatewayRouteCandidate>
	 */
	public function buildGatewayCandidates(IGateway $gateway, bool $prefixPublicId): array {
		return array_values(array_map(
			static fn (array $instance): GatewayRouteCandidate => GatewayRouteCandidate::fromGatewayAndInstance($gateway, $instance, $prefixPublicId),
			$this->gatewayConfigService->listInstances($gateway),
		));
	}

	/**
	 * @return list<GatewayRouteCandidate>
	 * @throws MessageTransmissionException
	 */
	public function resolveCandidatesForUser(IUser $user, string $gatewayId): array {
		$allCandidates = array_values(array_filter(
			$this->listResolvedInstances($gatewayId),
			static fn (GatewayRouteCandidate $candidate): bool => $candidate->instance->isComplete,
		));

		if ($allCandidates === []) {
			return [];
		}

		$userGroupIds = array_fill_keys($this->groupManager->getUserGroupIds($user), true);
		$groupCandidates = array_values(array_filter(
			$allCandidates,
			static fn (GatewayRouteCandidate $candidate): bool => $candidate->instance->matchesAnyGroup($userGroupIds),
		));

		if ($groupCandidates !== []) {
			usort($groupCandidates, [self::class, 'compareCandidates']);
			return $groupCandidates;
		}

		$openCandidates = array_values(array_filter(
			$allCandidates,
			static fn (GatewayRouteCandidate $candidate): bool => $candidate->instance->isOpenToAll(),
		));

		if ($openCandidates !== []) {
			usort($openCandidates, [self::class, 'compareCandidates']);
			return $openCandidates;
		}

		throw new MessageTransmissionException('No gateway instance is accessible for this user. Check group assignments in the gateway configuration.');
	}

	public static function compareCandidates(GatewayRouteCandidate $left, GatewayRouteCandidate $right): int {
		$priorityDiff = $right->instance->priority <=> $left->instance->priority;
		if ($priorityDiff !== 0) {
			return $priorityDiff;
		}

		$defaultDiff = (int)$right->instance->default <=> (int)$left->instance->default;
		if ($defaultDiff !== 0) {
			return $defaultDiff;
		}

		$createdAtDiff = strcmp($left->instance->createdAt, $right->instance->createdAt);
		if ($createdAtDiff !== 0) {
			return $createdAtDiff;
		}

		return strcmp($left->publicInstanceId, $right->publicInstanceId);
	}
}
