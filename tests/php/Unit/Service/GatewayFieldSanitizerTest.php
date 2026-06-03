<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\FieldExposure;
use OCA\TwoFactorGateway\Provider\FieldSensitivity;
use OCA\TwoFactorGateway\Provider\FieldType;
use OCA\TwoFactorGateway\Service\GatewayFieldSanitizer;
use OCA\TwoFactorGateway\Service\GatewayViewScope;
use PHPUnit\Framework\TestCase;

class GatewayFieldSanitizerTest extends TestCase {
	private GatewayFieldSanitizer $sanitizer;

	protected function setUp(): void {
		parent::setUp();
		$this->sanitizer = new GatewayFieldSanitizer();
	}

	public function testFilterFieldsKeepsAdminViewCompatibleAndDropsNeverFields(): void {
		$fields = [
			new FieldDefinition(field: 'base_url', prompt: 'Base URL'),
			new FieldDefinition(field: 'token', prompt: 'Token', type: FieldType::SECRET),
			new FieldDefinition(field: 'internal_only', prompt: 'Internal', exposure: FieldExposure::NEVER),
		];

		$visible = $this->sanitizer->filterFields($fields, GatewayViewScope::ADMIN);

		$this->assertSame(['base_url', 'token'], array_map(static fn (FieldDefinition $field): string => $field->field, $visible));
	}

	public function testSanitizeConfigDropsSecretAndAdminOnlyFieldsForDelegatedView(): void {
		$fields = [
			new FieldDefinition(field: 'display_name', prompt: 'Display name', exposure: FieldExposure::DELEGATED),
			new FieldDefinition(field: 'token', prompt: 'Token', type: FieldType::SECRET, exposure: FieldExposure::DELEGATED),
			new FieldDefinition(field: 'base_url', prompt: 'Base URL', exposure: FieldExposure::ADMIN),
		];
		$config = [
			'display_name' => 'Client A',
			'token' => 'super-secret',
			'base_url' => 'https://admin-only.example.com',
		];

		$sanitized = $this->sanitizer->sanitizeConfig($config, $fields, GatewayViewScope::DELEGATED);

		$this->assertSame(['display_name' => 'Client A'], $sanitized);
	}

	public function testSanitizeConfigKeepsOnlyRuntimeNonSecretFieldsForRuntimeView(): void {
		$fields = [
			new FieldDefinition(field: 'device_name', prompt: 'Device name', sensitivity: FieldSensitivity::NORMAL, exposure: FieldExposure::RUNTIME),
			new FieldDefinition(field: 'session_token', prompt: 'Session token', sensitivity: FieldSensitivity::SECRET, exposure: FieldExposure::RUNTIME),
			new FieldDefinition(field: 'label', prompt: 'Label', exposure: FieldExposure::DELEGATED),
		];
		$config = [
			'device_name' => 'GW-1',
			'session_token' => 'secret',
			'label' => 'Client A',
		];

		$sanitized = $this->sanitizer->sanitizeConfig($config, $fields, GatewayViewScope::RUNTIME);

		$this->assertSame(['device_name' => 'GW-1'], $sanitized);
	}
}
