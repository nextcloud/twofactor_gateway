<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Provider\Gateway\IGateway;

final class GatewayChoiceFormatter {
	/**
	 * @param array<string, IGateway> $gateways
	 * @return array<string, string>
	 */
	public static function gatewayLabels(array $gateways): array {
		$labels = [];
		foreach ($gateways as $gatewayId => $gateway) {
			$labels[$gatewayId] = sprintf('%s (%s)', $gateway->getSettings()->name, $gatewayId);
		}

		return $labels;
	}

	/**
	 * @param list<array{id: string, label: string, groupIds: list<string>, priority: int, ...}> $instances
	 * @return array<string, string>
	 */
	public static function instanceLabels(array $instances): array {
		$labels = [];
		foreach ($instances as $instance) {
			$labels[$instance['id']] = sprintf(
				'%s [%spriority: %d, groups: %s]',
				$instance['label'],
				$instance['id'] . ' | ',
				$instance['priority'],
				$instance['groupIds'] !== [] ? implode(', ', $instance['groupIds']) : 'none',
			);
		}

		return $labels;
	}

	/**
	 * @param array<string, string> $labelsById
	 */
	public static function resolveIdFromLabel(array $labelsById, string $selectedLabel): ?string {
		$gatewayId = array_search($selectedLabel, $labelsById, true);
		return $gatewayId === false ? null : $gatewayId;
	}
}
