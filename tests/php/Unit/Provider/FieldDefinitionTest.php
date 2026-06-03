<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider;

use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\FieldExposure;
use OCA\TwoFactorGateway\Provider\FieldSensitivity;
use OCA\TwoFactorGateway\Provider\FieldType;
use PHPUnit\Framework\TestCase;

class FieldDefinitionTest extends TestCase {
	public function testJsonSerializeIncludesHelperKeyAndPreservesProvidedValue(): void {
		$field = new FieldDefinition(
			field: 'api_id',
			prompt: 'API ID',
			helper: 'Get one at https://my.telegram.org/apps',
		);

		$serialized = $field->jsonSerialize();
		$this->assertArrayHasKey('helper', $serialized);
		$this->assertSame('Get one at https://my.telegram.org/apps', $serialized['helper']);
	}

	public function testJsonSerializeDefaultsHelperToEmptyString(): void {
		$field = new FieldDefinition(
			field: 'token',
			prompt: 'Token',
		);

		$serialized = $field->jsonSerialize();
		$this->assertArrayHasKey('helper', $serialized);
		$this->assertSame('', $serialized['helper']);
	}

	public function testJsonSerializeDerivesSecretSensitivityAndAdminExposureByDefault(): void {
		$field = new FieldDefinition(
			field: 'token',
			prompt: 'Token',
			type: FieldType::SECRET,
		);

		$serialized = $field->jsonSerialize();
		$this->assertSame('secret', $serialized['sensitivity']);
		$this->assertSame('admin', $serialized['exposure']);
	}

	public function testJsonSerializePreservesExplicitSensitivityAndExposure(): void {
		$field = new FieldDefinition(
			field: 'display_name',
			prompt: 'Display name',
			sensitivity: FieldSensitivity::NORMAL,
			exposure: FieldExposure::DELEGATED,
		);

		$serialized = $field->jsonSerialize();
		$this->assertSame('normal', $serialized['sensitivity']);
		$this->assertSame('delegated', $serialized['exposure']);
	}
}
