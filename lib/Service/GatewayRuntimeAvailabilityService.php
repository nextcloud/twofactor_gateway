<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCP\IUser;

class GatewayRuntimeAvailabilityService {
	public function __construct(
		private GatewayFactory $gatewayFactory,
		private GatewayRoutingService $gatewayRoutingService,
		private GatewayInstanceViewFactory $gatewayInstanceViewFactory,
	) {
	}

	public function getGateway(string $gatewayId): IGateway {
		return $this->gatewayFactory->get($gatewayId);
	}

	/**
	 * @return list<GatewayRouteCandidate>
	 * @throws MessageTransmissionException
	 */
	public function resolveCandidatesForUser(IUser $user, string $gatewayId): array {
		return $this->gatewayRoutingService->resolveCandidatesForUser($user, $gatewayId);
	}

	public function hasDirectGatewayFallback(string $gatewayId): bool {
		return $this->getGateway($gatewayId)->isComplete();
	}

	/** @return list<array<string, mixed>> */
	public function listGatewaysForUser(IUser $user): array {
		$entries = [];
		foreach ($this->gatewayFactory->getFqcnList() as $fqcn) {
			$gateway = $this->gatewayFactory->get($fqcn);
			$entry = $this->createGatewayEntryForUser($user, $gateway);
			if ($entry['instances'] !== [] || $entry['hasDirectGatewayFallback']) {
				$entries[] = $entry;
			}
		}

		return array_values($entries);
	}

	/** @return array<string, mixed> */
	public function createGatewayEntryForUser(IUser $user, IGateway $gateway): array {
		$entry = $this->gatewayInstanceViewFactory->createGatewayEntry($gateway, [], GatewayViewScope::RUNTIME);
		$entry['instances'] = $this->listAvailableInstancesForUser($user, $gateway->getProviderId());
		$entry['hasDirectGatewayFallback'] = $gateway->isComplete();
		return $entry;
	}

	/**
	 * @return list<array{id: string, publicInstanceId: string, providerId: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}>
	 */
	public function listAvailableInstancesForUser(IUser $user, string $gatewayId): array {
		try {
			$candidates = $this->resolveCandidatesForUser($user, $gatewayId);
		} catch (MessageTransmissionException) {
			return [];
		}

		return array_values(array_map(
			fn (GatewayRouteCandidate $candidate): array => $this->createAvailableInstanceView($candidate),
			$candidates,
		));
	}

	/**
	 * @return array{id: string, publicInstanceId: string, providerId: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}
	 */
	public function createAvailableInstanceView(GatewayRouteCandidate $candidate): array {
		$view = $this->gatewayInstanceViewFactory->createInstanceView(
			$candidate->gateway,
			$candidate->instance->toArray(),
			GatewayViewScope::RUNTIME,
		);

		return [
			...$view,
			'providerId' => $candidate->providerId,
			'publicInstanceId' => $candidate->publicInstanceId,
		];
	}
}
