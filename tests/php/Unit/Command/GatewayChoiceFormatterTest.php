<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Command;

use OCA\TwoFactorGateway\Command\GatewayChoiceFormatter;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;

class GatewayChoiceFormatterTest extends AppTestCase {
	public function testGatewayLabelsUseFriendlyNameOnly(): void {
		$signalGateway = $this->createMock(IGateway::class);
		$signalGateway->method('getSettings')->willReturn(new Settings(name: 'Signal', id: 'signal'));

		$goWhatsAppGateway = $this->createMock(IGateway::class);
		$goWhatsAppGateway->method('getSettings')->willReturn(new Settings(name: 'WhatsApp web', id: 'gowhatsapp'));

		$labels = GatewayChoiceFormatter::gatewayLabels([
			'signal' => $signalGateway,
			'gowhatsapp' => $goWhatsAppGateway,
		]);

		$this->assertSame('Signal', $labels['signal']);
		$this->assertSame('WhatsApp web', $labels['gowhatsapp']);
	}

	public function testInstanceLabelsUseFriendlyDescription(): void {
		$labels = GatewayChoiceFormatter::instanceLabels([
			[
				'id' => 'gw-1',
				'label' => 'Ops',
				'groupIds' => ['admins', 'staff'],
				'priority' => 20,
			],
			[
				'id' => 'gw-2',
				'label' => 'Fallback',
				'groupIds' => [],
				'priority' => 0,
			],
		]);

		$this->assertSame('Ops [gw-1 | priority: 20, groups: admins, staff]', $labels['gw-1']);
		$this->assertSame('Fallback [gw-2 | priority: 0, groups: none]', $labels['gw-2']);
	}

	public function testResolveIdFromLabelReturnsMatchingId(): void {
		$labels = [
			'signal' => 'Signal',
			'gowhatsapp' => 'WhatsApp web',
		];

		$this->assertSame('gowhatsapp', GatewayChoiceFormatter::resolveIdFromLabel($labels, 'WhatsApp web'));
		$this->assertNull(GatewayChoiceFormatter::resolveIdFromLabel($labels, 'Missing'));
	}
}
