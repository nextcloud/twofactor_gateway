<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\FieldExposure;
use OCA\TwoFactorGateway\Provider\FieldType;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Service\GatewayFieldSanitizer;
use OCA\TwoFactorGateway\Service\GatewayInstanceViewFactory;
use OCA\TwoFactorGateway\Service\GatewayViewScope;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GatewayInstanceViewFactoryTest extends TestCase {
	private GatewayInstanceViewFactory $factory;

	protected function setUp(): void {
		parent::setUp();
		$this->factory = new GatewayInstanceViewFactory(new GatewayFieldSanitizer());
	}

	public function testCreateInstanceViewSanitizesDelegatedConfig(): void {
		$gateway = $this->makeGatewayMock('sms', [
			new FieldDefinition(field: 'display_name', prompt: 'Display name', exposure: FieldExposure::DELEGATED),
			new FieldDefinition(field: 'token', prompt: 'Token', type: FieldType::SECRET),
		]);

		$view = $this->factory->createInstanceView($gateway, [
			'id' => 'inst-a',
			'label' => 'Client A',
			'default' => true,
			'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['display_name' => 'Client A', 'token' => 'secret'],
			'isComplete' => true,
			'groupIds' => ['admins'],
			'priority' => 10,
		], GatewayViewScope::DELEGATED);

		$this->assertSame(['display_name' => 'Client A'], $view['config']);
		$this->assertSame(['admins'], $view['groupIds']);
	}

	public function testCreateGatewayEntrySanitizesCatalogFieldsForDelegatedView(): void {
		/** @var IGateway&IProviderCatalogGateway&MockObject $gateway */
		$gateway = $this->createMockForIntersectionOfInterfaces([IGateway::class, IProviderCatalogGateway::class]);
		$settings = new Settings(name: 'WhatsApp', id: 'whatsapp', fields: [new FieldDefinition(field: 'base_url', prompt: 'Base URL')]);
		$gateway->method('getProviderId')->willReturn('whatsapp');
		$gateway->method('getSettings')->willReturn($settings);
		$gateway->method('getProviderSelectorField')->willReturn(new FieldDefinition(field: 'provider', prompt: 'Provider'));
		$gateway->method('getProviderCatalog')->willReturn([
			[
				'id' => 'gowhatsapp',
				'name' => 'GoWhatsApp',
				'fields' => [
					new FieldDefinition(field: 'display_name', prompt: 'Display name', exposure: FieldExposure::DELEGATED),
					new FieldDefinition(field: 'token', prompt: 'Token', type: FieldType::SECRET, exposure: FieldExposure::DELEGATED),
				],
			],
		]);

		$entry = $this->factory->createGatewayEntry($gateway, [[
			'id' => 'inst-wa',
			'label' => 'WA Prod',
			'default' => true,
			'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['provider' => 'gowhatsapp', 'display_name' => 'WA Prod', 'token' => 'secret'],
			'isComplete' => true,
			'groupIds' => ['client-a'],
			'priority' => 5,
		]], GatewayViewScope::DELEGATED);

		$this->assertArrayNotHasKey('providerSelector', $entry);
		$this->assertSame(['display_name'], array_map(static fn (array $field): string => $field['field'], $entry['providerCatalog'][0]['fields']));
		$this->assertSame(['display_name' => 'WA Prod'], $entry['instances'][0]['config']);
	}

	/**
	 * @param list<FieldDefinition> $fields
	 * @return IGateway&MockObject
	 */
	private function makeGatewayMock(string $id, array $fields): IGateway&MockObject {
		$settings = new Settings(name: ucfirst($id), id: $id, fields: $fields);
		$gateway = $this->createMock(IGateway::class);
		$gateway->method('getProviderId')->willReturn($id);
		$gateway->method('getSettings')->willReturn($settings);
		return $gateway;
	}
}
