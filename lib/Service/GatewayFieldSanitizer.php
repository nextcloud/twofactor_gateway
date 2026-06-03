<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\FieldExposure;
use OCA\TwoFactorGateway\Provider\FieldSensitivity;

class GatewayFieldSanitizer {
	/**
	 * @param list<FieldDefinition> $fields
	 * @return list<FieldDefinition>
	 */
	public function filterFields(array $fields, GatewayViewScope $scope): array {
		return array_values(array_filter(
			$fields,
			fn (FieldDefinition $field): bool => $this->shouldExposeField($field, $scope),
		));
	}

	/**
	 * @param array<string, string> $config
	 * @param list<FieldDefinition> $fields
	 * @return array<string, string>
	 */
	public function sanitizeConfig(array $config, array $fields, GatewayViewScope $scope): array {
		$visibleFieldNames = [];
		foreach ($this->filterFields($fields, $scope) as $field) {
			$visibleFieldNames[$field->field] = true;
		}

		return array_intersect_key($config, $visibleFieldNames);
	}

	private function shouldExposeField(FieldDefinition $field, GatewayViewScope $scope): bool {
		$exposure = FieldExposure::fromNullable($field->getExposure());
		if ($exposure === FieldExposure::NEVER) {
			return false;
		}

		if ($scope === GatewayViewScope::ADMIN) {
			return true;
		}

		$sensitivity = FieldSensitivity::fromNullable($field->getSensitivity());
		if ($sensitivity === FieldSensitivity::SECRET) {
			return false;
		}

		return match ($scope) {
			GatewayViewScope::DELEGATED => $exposure === FieldExposure::DELEGATED,
			GatewayViewScope::RUNTIME => $exposure === FieldExposure::RUNTIME,
			GatewayViewScope::ADMIN => true,
		};
	}
}
