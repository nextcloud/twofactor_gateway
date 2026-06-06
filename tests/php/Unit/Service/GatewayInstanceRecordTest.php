<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Service\GatewayInstanceRecord;
use PHPUnit\Framework\TestCase;

class GatewayInstanceRecordTest extends TestCase {
	public function testFromArrayPreservesCreatedByUserId(): void {
		$record = GatewayInstanceRecord::fromArray([
			'id' => 'inst-1',
			'label' => 'Gateway A',
			'default' => true,
			'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['token' => 'secret'],
			'isComplete' => true,
			'groupIds' => ['team-a'],
			'priority' => 10,
			'createdByUserId' => 'delegated-admin',
		]);

		$this->assertSame('delegated-admin', $record->createdByUserId);
		$this->assertSame('delegated-admin', $record->toArray()['createdByUserId']);
	}

	public function testFromArrayDefaultsCreatedByUserIdToNullWhenMissing(): void {
		$record = GatewayInstanceRecord::fromArray([
			'id' => 'inst-2',
			'label' => 'Gateway B',
			'default' => false,
			'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['url' => 'https://sms.example.com'],
			'isComplete' => true,
			'groupIds' => [],
			'priority' => 0,
		]);

		$this->assertNull($record->createdByUserId);
		$this->assertArrayHasKey('createdByUserId', $record->toArray());
		$this->assertNull($record->toArray()['createdByUserId']);
	}
}
