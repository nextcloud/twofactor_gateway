<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

final class GatewayInstanceRecord {
	/**
	 * @param array<string, string> $config
	 * @param list<string> $groupIds
	 */
	public function __construct(
		public string $id,
		public string $label,
		public bool $default,
		public string $createdAt,
		public array $config,
		public bool $isComplete,
		public array $groupIds,
		public int $priority,
	) {
	}

	/**
	 * @param array{id: string, label?: string, default?: bool, createdAt?: string, config?: array<string, scalar|null>, isComplete?: bool, groupIds?: list<string>, priority?: int} $instance
	 */
	public static function fromArray(array $instance): self {
		$config = [];
		foreach (($instance['config'] ?? []) as $key => $value) {
			$config[(string)$key] = (string)$value;
		}

		$groupIds = array_values(array_map(
			static fn ($groupId): string => (string)$groupId,
			is_array($instance['groupIds'] ?? null) ? $instance['groupIds'] : [],
		));

		return new self(
			id: (string)$instance['id'],
			label: (string)($instance['label'] ?? ''),
			default: (bool)($instance['default'] ?? false),
			createdAt: (string)($instance['createdAt'] ?? ''),
			config: $config,
			isComplete: (bool)($instance['isComplete'] ?? false),
			groupIds: $groupIds,
			priority: is_numeric($instance['priority'] ?? null) ? (int)$instance['priority'] : 0,
		);
	}

	/**
	 * @return array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int}
	 */
	public function toArray(): array {
		return [
			'id' => $this->id,
			'label' => $this->label,
			'default' => $this->default,
			'createdAt' => $this->createdAt,
			'config' => $this->config,
			'isComplete' => $this->isComplete,
			'groupIds' => $this->groupIds,
			'priority' => $this->priority,
		];
	}

	/**
	 * @param array<string, true> $userGroupIds
	 */
	public function matchesAnyGroup(array $userGroupIds): bool {
		foreach ($this->groupIds as $groupId) {
			if (isset($userGroupIds[$groupId])) {
				return true;
			}
		}

		return false;
	}

	public function isOpenToAll(): bool {
		return $this->groupIds === [];
	}
}
