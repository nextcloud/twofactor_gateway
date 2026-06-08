<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCP\IGroupManager;
use OCP\IUser;

/**
 * @psalm-import-type TwoFactorGatewayAdminScreen from \OCA\TwoFactorGateway\ResponseDefinitions
 * @psalm-import-type TwoFactorGatewayAdminScreenItem from \OCA\TwoFactorGateway\ResponseDefinitions
 * @psalm-import-type TwoFactorGatewayAllowedActions from \OCA\TwoFactorGateway\ResponseDefinitions
 * @psalm-import-type TwoFactorGatewayGroup from \OCA\TwoFactorGateway\ResponseDefinitions
 */
class GatewayAdminScreenService {
	public function __construct(
		private GatewayCatalogService $gatewayCatalogService,
		private GatewayPermissionService $gatewayPermissionService,
		private IGroupManager $groupManager,
	) {
	}

	/** @return TwoFactorGatewayAdminScreen */
	public function build(?IUser $actor, int $groupLimit = 200): array {
		$gateways = $this->gatewayCatalogService->listGateways($actor);
		$groups = $this->listAssignableGroups($actor, $groupLimit);

		return [
			'gateways' => $gateways,
			'groups' => $groups,
			'allowedActions' => $this->buildAllowedActions($actor, $groups),
			'items' => $this->buildItems($gateways, $groups),
		];
	}

	/**
	 * @param list<TwoFactorGatewayGroup> $groups
	 * @return TwoFactorGatewayAllowedActions
	 */
	private function buildAllowedActions(?IUser $actor, array $groups): array {
		$scope = $this->gatewayPermissionService->resolveViewScope($actor);
		$canView = $scope !== GatewayViewScope::RUNTIME;
		$canCreateInstances = match ($scope) {
			GatewayViewScope::ADMIN => true,
			GatewayViewScope::DELEGATED => $groups !== [],
			GatewayViewScope::RUNTIME => false,
		};

		return [
			'canView' => $canView,
			'canCreateInstances' => $canCreateInstances,
			'canEditInstances' => $canView,
			'canDeleteInstances' => $canView,
			'canSetDefaultInstances' => $canView,
			'canManageRouting' => $canView,
			'canTestInstances' => $canView,
			'canReorderInstances' => $canView,
		];
	}

	/**
	 * @param list<array<string, mixed>> $gateways
	 * @param list<TwoFactorGatewayGroup> $groups
	 * @return list<TwoFactorGatewayAdminScreenItem>
	 */
	private function buildItems(array $gateways, array $groups): array {
		$groupNamesById = [];
		foreach ($groups as $group) {
			$groupNamesById[(string)$group['id']] = (string)$group['displayName'];
		}

		$items = [];
		foreach ($gateways as $gateway) {
			$gatewayId = trim((string)($gateway['id'] ?? ''));
			if ($gatewayId === '') {
				continue;
			}

			$gatewayName = trim((string)($gateway['name'] ?? $gatewayId));
			$gatewayFields = is_array($gateway['fields'] ?? null) ? array_values($gateway['fields']) : [];
			$providerCatalog = is_array($gateway['providerCatalog'] ?? null) ? array_values($gateway['providerCatalog']) : [];
			$selectorField = is_array($gateway['providerSelector'] ?? null)
				? trim((string)($gateway['providerSelector']['field'] ?? 'provider'))
				: 'provider';
			$instances = is_array($gateway['instances'] ?? null) ? array_values($gateway['instances']) : [];

			foreach ($instances as $instance) {
				if (!is_array($instance)) {
					continue;
				}

				$instanceId = trim((string)($instance['id'] ?? ''));
				if ($instanceId === '') {
					continue;
				}

				$config = is_array($instance['config'] ?? null) ? $instance['config'] : [];
				$selectedProviderId = trim((string)($config[$selectorField] ?? ''));
				$selectedProvider = $this->findProviderCatalogEntry($providerCatalog, $selectedProviderId);
				$groupIds = $this->normalizeStringList($instance['groupIds'] ?? []);
				$priority = isset($instance['priority']) ? (int)$instance['priority'] : 0;
				$providerId = trim((string)($instance['providerId'] ?? $gatewayId));

				$items[] = [
					'orderKey' => $gatewayId . ':' . $instanceId,
					'gatewayId' => $gatewayId,
					'providerName' => $selectedProvider['name'] ?? $gatewayName,
					'fields' => is_array($selectedProvider['fields'] ?? null)
						? array_values($selectedProvider['fields'])
						: $gatewayFields,
					'instance' => [
						...$instance,
						'providerId' => $providerId,
						'groupIds' => $groupIds,
						'priority' => $priority,
					],
					'groupNames' => $this->resolveGroupNames($groupIds, $groupNamesById),
					'showRoutingAction' => true,
				];
			}
		}

		usort($items, static function (array $left, array $right): int {
			$priorityDiff = ((int)($right['instance']['priority'] ?? 0)) <=> ((int)($left['instance']['priority'] ?? 0));
			if ($priorityDiff !== 0) {
				return $priorityDiff;
			}

			$labelDiff = strcasecmp(
				trim((string)($left['instance']['label'] ?? '')),
				trim((string)($right['instance']['label'] ?? '')),
			);
			if ($labelDiff !== 0) {
				return $labelDiff;
			}

			return strcmp((string)$left['orderKey'], (string)$right['orderKey']);
		});

		return array_values($items);
	}

	/**
	 * @param list<array<string, mixed>> $providerCatalog
	 * @return array<string, mixed>|null
	 */
	private function findProviderCatalogEntry(array $providerCatalog, string $providerId): ?array {
		if ($providerId === '') {
			return null;
		}

		foreach ($providerCatalog as $provider) {
			if (!is_array($provider)) {
				continue;
			}

			if (trim((string)($provider['id'] ?? '')) === $providerId) {
				return $provider;
			}
		}

		return null;
	}

	/**
	 * @param list<string> $groupIds
	 * @param array<string, string> $groupNamesById
	 * @return list<string>
	 */
	private function resolveGroupNames(array $groupIds, array $groupNamesById): array {
		$groupNames = array_map(
			static fn (string $groupId): string => $groupNamesById[$groupId] ?? $groupId,
			$groupIds,
		);

		return array_values(array_unique(array_filter($groupNames, static fn (string $groupName): bool => $groupName !== '')));
	}

	/**
	 * @param list<string>|mixed $values
	 * @return list<string>
	 */
	private function normalizeStringList(mixed $values): array {
		if (!is_array($values)) {
			return [];
		}

		return array_values(array_unique(array_filter(array_map(
			static fn (mixed $value): string => trim((string)$value),
			$values,
		), static fn (string $value): bool => $value !== '')));
	}

	/**
	 * @return list<TwoFactorGatewayGroup>
	 */
	private function listAssignableGroups(?IUser $actor, int $limit): array {
		$limit = max(1, min(500, $limit));
		$matchingGroups = $this->groupManager->search('', $limit, 0);
		$assignableGroups = $this->gatewayPermissionService->filterAssignableGroups($actor, $matchingGroups);
		$groups = array_map(
			static fn ($group): array => [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			],
			$assignableGroups,
		);

		usort($groups, static fn (array $left, array $right): int => strcasecmp($left['displayName'], $right['displayName']));

		return $groups;
	}
}
