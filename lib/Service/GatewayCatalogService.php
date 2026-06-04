<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCP\IUser;

class GatewayCatalogService {
	public function __construct(
		private GatewayFactory $gatewayFactory,
		private GatewayConfigService $configService,
		private GatewayInstanceViewFactory $gatewayInstanceViewFactory,
		private GatewayPermissionService $gatewayPermissionService,
	) {
	}

	/** @return list<array<string, mixed>> */
	public function listGateways(?IUser $actor): array {
		return array_values(array_map(
			fn (string $fqcn): array => $this->createGatewayEntry($actor, $this->gatewayFactory->get($fqcn)),
			$this->gatewayFactory->getFqcnList(),
		));
	}

	/** @return array<string, mixed> */
	public function createGatewayEntry(?IUser $actor, IGateway $gateway): array {
		$scope = $this->gatewayPermissionService->resolveViewScope($actor);
		$instances = $this->gatewayPermissionService->filterVisibleInstances($actor, $this->configService->listInstances($gateway));

		return $this->gatewayInstanceViewFactory->createGatewayEntry($gateway, $instances, $scope);
	}

	/**
	 * @param array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int} $instance
	 * @return array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}
	 */
	public function createInstanceView(?IUser $actor, IGateway $gateway, array $instance): array {
		$scope = $this->gatewayPermissionService->resolveViewScope($actor);
		return $this->gatewayInstanceViewFactory->createInstanceView($gateway, $instance, $scope);
	}
}
