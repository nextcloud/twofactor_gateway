<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\WhatsApp;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Factory;
use PHPUnit\Framework\TestCase;

class FactoryTest extends TestCase {
	public function testDriverGatewayListContainsGoWhatsAppGateway(): void {
		$factory = new Factory();

		$this->assertContains(
			'OCA\\TwoFactorGateway\\Provider\\Channel\\WhatsApp\\Provider\\Drivers\\GoWhatsApp\\Gateway',
			$factory->getFqcnList(),
		);
	}
}
