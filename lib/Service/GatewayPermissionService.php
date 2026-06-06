<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCA\TwoFactorGateway\Exception\GatewayPermissionDeniedException;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;

/** @psalm-import-type GatewayInstanceArray from GatewayInstanceRecord */
class GatewayPermissionService {
	public function __construct(
		private IGroupManager $groupManager,
		private ISubAdmin $subAdmin,
	) {
	}

	public function resolveViewScope(?IUser $actor): GatewayViewScope {
		if ($actor === null) {
			return GatewayViewScope::RUNTIME;
		}

		if ($this->groupManager->isAdmin($actor->getUID())) {
			return GatewayViewScope::ADMIN;
		}

		if ($this->groupManager->isDelegatedAdmin($actor->getUID())) {
			return GatewayViewScope::DELEGATED;
		}

		return GatewayViewScope::RUNTIME;
	}

	/** @param array{groupIds?: list<string>, ...} $instance */
	public function canViewInstance(?IUser $actor, array $instance): bool {
		return $this->canAccessScopedInstance($actor, $instance);
	}

	/** @param array{groupIds?: list<string>, ...} $instance */
	public function canEditInstance(?IUser $actor, array $instance): bool {
		return $this->canAccessScopedInstance($actor, $instance);
	}

	/** @param array{groupIds?: list<string>, ...} $instance */
	public function canDeleteInstance(?IUser $actor, array $instance): bool {
		return $this->canAccessScopedInstance($actor, $instance);
	}

	/**
	 * @param list<string> $groupIds
	 */
	public function canCreateInstanceForGroups(?IUser $actor, array $groupIds): bool {
		if ($this->isAdmin($actor)) {
			return true;
		}

		if (!$this->isDelegatedAdmin($actor)) {
			return false;
		}

		return $this->groupIdsWithinDelegatedScope($actor, $groupIds);
	}

	/** @param array{groupIds?: list<string>, ...} $instance */
	public function canManageRouting(?IUser $actor, array $instance): bool {
		return $this->canAccessScopedInstance($actor, $instance);
	}

	/**
	 * @param list<IGroup> $groups
	 * @return list<IGroup>
	 */
	public function filterAssignableGroups(?IUser $actor, array $groups): array {
		if ($this->isAdmin($actor)) {
			return array_values($groups);
		}

		if (!$this->isDelegatedAdmin($actor)) {
			return [];
		}

		$allowedGroupIds = $this->delegatedAllowedGroupIds($actor);

		return array_values(array_filter(
			$groups,
			static fn (IGroup $group): bool => isset($allowedGroupIds[$group->getGID()]),
		));
	}

	/**
	 * @param list<GatewayInstanceArray> $instances
	 * @return list<GatewayInstanceArray>
	 */
	public function filterVisibleInstances(?IUser $actor, array $instances): array {
		return array_values(array_filter(
			$instances,
			fn (array $instance): bool => $this->canViewInstance($actor, $instance),
		));
	}

	/**
	 * @param array{groupIds?: list<string>, ...} $instance
	 * @throws GatewayPermissionDeniedException
	 */
	public function assertCanViewInstance(?IUser $actor, array $instance): void {
		if ($this->canViewInstance($actor, $instance)) {
			return;
		}

		throw new GatewayPermissionDeniedException('You are not allowed to view this gateway instance.');
	}

	/**
	 * @param array{groupIds?: list<string>, ...} $instance
	 * @throws GatewayPermissionDeniedException
	 */
	public function assertCanEditInstance(?IUser $actor, array $instance): void {
		if ($this->canEditInstance($actor, $instance)) {
			return;
		}

		throw new GatewayPermissionDeniedException('You are not allowed to edit this gateway instance.');
	}

	/**
	 * @param array{groupIds?: list<string>, ...} $instance
	 * @throws GatewayPermissionDeniedException
	 */
	public function assertCanDeleteInstance(?IUser $actor, array $instance): void {
		if ($this->canDeleteInstance($actor, $instance)) {
			return;
		}

		throw new GatewayPermissionDeniedException('You are not allowed to delete this gateway instance.');
	}

	/**
	 * @param list<string> $groupIds
	 * @throws GatewayPermissionDeniedException
	 */
	public function assertCanCreateInstanceForGroups(?IUser $actor, array $groupIds): void {
		if ($this->canCreateInstanceForGroups($actor, $groupIds)) {
			return;
		}

		throw new GatewayPermissionDeniedException('You are not allowed to create or assign gateway instances outside your group scope.');
	}

	/**
	 * @param array{groupIds?: list<string>, ...} $instance
	 * @throws GatewayPermissionDeniedException
	 */
	public function assertCanManageRouting(?IUser $actor, array $instance): void {
		if ($this->canManageRouting($actor, $instance)) {
			return;
		}

		throw new GatewayPermissionDeniedException('You are not allowed to manage routing for this gateway instance.');
	}

	/** @param array{groupIds?: list<string>, ...} $instance */
	private function canAccessScopedInstance(?IUser $actor, array $instance): bool {
		if ($this->isAdmin($actor)) {
			return true;
		}

		if (!$this->isDelegatedAdmin($actor)) {
			return false;
		}

		$groupIds = $this->normalizeGroupIds($instance['groupIds'] ?? []);
		if ($groupIds === []) {
			return false;
		}

		return $this->groupIdsWithinDelegatedScope($actor, $groupIds);
	}

	private function isAdmin(?IUser $actor): bool {
		return $actor !== null && $this->groupManager->isAdmin($actor->getUID());
	}

	private function isDelegatedAdmin(?IUser $actor): bool {
		return $actor !== null && $this->groupManager->isDelegatedAdmin($actor->getUID());
	}

	/**
	 * @param list<string> $groupIds
	 */
	private function groupIdsWithinDelegatedScope(IUser $actor, array $groupIds): bool {
		$groupIds = $this->normalizeGroupIds($groupIds);
		if ($groupIds === []) {
			return false;
		}

		$allowedGroupIds = $this->delegatedAllowedGroupIds($actor);

		foreach ($groupIds as $groupId) {
			if (!isset($allowedGroupIds[$groupId])) {
				return false;
			}
		}

		return true;
	}

	/** @return array<string, true> */
	private function delegatedAllowedGroupIds(IUser $actor): array {
		return array_fill_keys(array_map(
			static fn (IGroup $group): string => $group->getGID(),
			$this->subAdmin->getSubAdminsGroups($actor),
		), true);
	}

	/**
	 * @param list<string> $groupIds
	 * @return list<string>
	 */
	private function normalizeGroupIds(array $groupIds): array {
		$normalized = array_values(array_unique(array_filter(array_map(
			static fn ($groupId): string => trim((string)$groupId),
			$groupIds,
		), static fn (string $groupId): bool => $groupId !== '')));
		sort($normalized);
		return $normalized;
	}
}
