<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider;

use OCA\TwoFactorGateway\Provider\FieldDefinition;
use PHPUnit\Framework\TestCase;

class FieldDefinitionTest extends TestCase {
	public function testJsonSerializeIncludesHelperText(): void {
		$field = new FieldDefinition(
			field: 'api_id',
			prompt: 'API ID',
			helper: 'Get one at https://my.telegram.org/apps',
		);

		$this->assertSame('Get one at https://my.telegram.org/apps', $field->jsonSerialize()['helper']);
	}
}
