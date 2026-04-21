<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Exception\GatewayInstanceNotFoundException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Service\GatewayConfigService;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;

class GatewayConfigServiceTest extends AppTestCase {
	private GatewayConfigService $service;
	private GatewayFactory&MockObject $gatewayFactory;
	private IAppConfig $appConfig;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->makeInMemoryAppConfig();
		$this->gatewayFactory = $this->createMock(GatewayFactory::class);
		$this->service = new GatewayConfigService($this->appConfig, $this->gatewayFactory);
	}

	private function makeGatewayMock(string $id, string $name, array $fieldDefs): IGateway&MockObject {
		$fields = array_map(
			fn (array $f) => new FieldDefinition($f['field'], $f['prompt'], $f['default'] ?? '', $f['optional'] ?? false, $f['type'] ?? null, $f['hidden'] ?? false, $f['min'] ?? null, $f['max'] ?? null),
			$fieldDefs,
		);
		$settings = new Settings(name: $name, id: $id, fields: $fields);
		$gateway = $this->createMock(IGateway::class);
		$gateway->method('getProviderId')->willReturn($id);
		$gateway->method('getSettings')->willReturn($settings);
		return $gateway;
	}

	private function makeCatalogGatewayMock(string $id, string $name, array $fieldDefs, string $selectorField, array $catalog): IGateway&IProviderCatalogGateway&MockObject {
		$fields = array_map(
			fn (array $f) => new FieldDefinition($f['field'], $f['prompt'], $f['default'] ?? '', $f['optional'] ?? false, $f['type'] ?? null, $f['hidden'] ?? false, $f['min'] ?? null, $f['max'] ?? null),
			$fieldDefs,
		);
		$settings = new Settings(name: $name, id: $id, fields: $fields);
		/** @var IGateway&IProviderCatalogGateway&MockObject $gateway */
		$gateway = $this->createMockForIntersectionOfInterfaces([IGateway::class, IProviderCatalogGateway::class]);
		$gateway->method('getProviderId')->willReturn($id);
		$gateway->method('getSettings')->willReturn($settings);
		$gateway->method('getProviderSelectorField')->willReturn(new FieldDefinition($selectorField, 'Provider', 'gowhatsapp', false, null, true));
		$gateway->method('getProviderCatalog')->willReturn(array_map(
			static function (array $provider): array {
				return [
					'id' => $provider['id'],
					'name' => $provider['name'],
					'fields' => array_map(
						static fn (array $field): FieldDefinition => new FieldDefinition(
							$field['field'],
							$field['prompt'],
							$field['default'] ?? '',
							$field['optional'] ?? false,
							$field['type'] ?? null,
							$field['hidden'] ?? false,
							$field['min'] ?? null,
							$field['max'] ?? null,
						),
						$provider['fields'],
					),
				];
			},
			$catalog,
		));
		return $gateway;
	}

	public function testListInstancesReturnsEmptyWhenNoneCreated(): void {
		$gateway = $this->makeGatewayMock('sms', 'SMS', [['field' => 'url', 'prompt' => 'URL']]);

		$instances = $this->service->listInstances($gateway);

		$this->assertSame([], $instances);
	}

	public function testCreateInstanceStoresConfigAndReturnsRecord(): void {
		$gateway = $this->makeGatewayMock('telegram', 'Telegram', [
			['field' => 'token', 'prompt' => 'Bot Token'],
		]);

		$instance = $this->service->createInstance($gateway, 'Production', ['token' => 'abc123']);

		$this->assertNotEmpty($instance['id']);
		$this->assertSame('Production', $instance['label']);
		$this->assertSame(['token' => 'abc123'], $instance['config']);
		$this->assertArrayHasKey('createdAt', $instance);
	}

	public function testFirstCreatedInstanceBecomesDefault(): void {
		$gateway = $this->makeGatewayMock('signal', 'Signal', [
			['field' => 'number', 'prompt' => 'Number'],
		]);

		$instance = $this->service->createInstance($gateway, 'Main', ['number' => '+1234567890']);

		$this->assertTrue($instance['default']);
	}

	public function testSecondCreatedInstanceIsNotDefault(): void {
		$gateway = $this->makeGatewayMock('signal', 'Signal', [
			['field' => 'number', 'prompt' => 'Number'],
		]);
		$this->service->createInstance($gateway, 'First', ['number' => '+1111111111']);

		$second = $this->service->createInstance($gateway, 'Second', ['number' => '+2222222222']);

		$this->assertFalse($second['default']);
	}

	public function testListInstancesReturnsAllInstances(): void {
		$gateway = $this->makeGatewayMock('sms', 'SMS', [
			['field' => 'url', 'prompt' => 'URL'],
		]);
		$this->service->createInstance($gateway, 'Client A', ['url' => 'https://a.example.com']);
		$this->service->createInstance($gateway, 'Client B', ['url' => 'https://b.example.com']);

		$instances = $this->service->listInstances($gateway);

		$this->assertCount(2, $instances);
		$labels = array_column($instances, 'label');
		$this->assertContains('Client A', $labels);
		$this->assertContains('Client B', $labels);
	}

	public function testGetInstanceReturnsCorrectRecord(): void {
		$gateway = $this->makeGatewayMock('xmpp', 'XMPP', [
			['field' => 'host', 'prompt' => 'Host'],
		]);
		$created = $this->service->createInstance($gateway, 'My XMPP', ['host' => 'xmpp.example.com']);
		$instanceId = $created['id'];

		$fetched = $this->service->getInstance($gateway, $instanceId);

		$this->assertSame($instanceId, $fetched['id']);
		$this->assertSame('My XMPP', $fetched['label']);
		$this->assertSame(['host' => 'xmpp.example.com'], $fetched['config']);
	}

	public function testGetInstanceThrowsWhenNotFound(): void {
		$gateway = $this->makeGatewayMock('sms', 'SMS', []);

		$this->expectException(GatewayInstanceNotFoundException::class);

		$this->service->getInstance($gateway, 'nonexistent-id');
	}

	public function testUpdateInstanceChangesLabelAndConfig(): void {
		$gateway = $this->makeGatewayMock('telegram', 'Telegram', [
			['field' => 'token', 'prompt' => 'Token'],
		]);
		$created = $this->service->createInstance($gateway, 'Old Label', ['token' => 'old-token']);

		$updated = $this->service->updateInstance($gateway, $created['id'], 'New Label', ['token' => 'new-token']);

		$this->assertSame('New Label', $updated['label']);
		$this->assertSame(['token' => 'new-token'], $updated['config']);
	}

	public function testCreateAndUpdateInstancePersistRoutingMetadata(): void {
		$gateway = $this->makeGatewayMock('sms', 'SMS', [
			['field' => 'url', 'prompt' => 'URL'],
		]);

		$created = $this->service->createInstance(
			$gateway,
			'Routed',
			['url' => 'https://sms.example.com'],
			['ops', ' admins ', 'ops'],
			15,
		);

		$this->assertSame(['admins', 'ops'], $created['groupIds']);
		$this->assertSame(15, $created['priority']);

		$updated = $this->service->updateInstance(
			$gateway,
			$created['id'],
			'Routed',
			['url' => 'https://sms2.example.com'],
			['staff'],
			30,
		);

		$this->assertSame(['staff'], $updated['groupIds']);
		$this->assertSame(30, $updated['priority']);

		$fetched = $this->service->getInstance($gateway, $created['id']);
		$this->assertSame(['staff'], $fetched['groupIds']);
		$this->assertSame(30, $fetched['priority']);
	}

	public function testDeleteInstanceRemovesItFromList(): void {
		$gateway = $this->makeGatewayMock('signal', 'Signal', [
			['field' => 'number', 'prompt' => 'Number'],
		]);
		$created = $this->service->createInstance($gateway, 'MySignal', ['number' => '+1234567890']);

		$this->service->deleteInstance($gateway, $created['id']);

		$instances = $this->service->listInstances($gateway);
		$this->assertCount(0, $instances);
	}

	public function testDeleteInstanceThrowsWhenNotFound(): void {
		$gateway = $this->makeGatewayMock('signal', 'Signal', [
			['field' => 'number', 'prompt' => 'Number'],
		]);

		$this->expectException(GatewayInstanceNotFoundException::class);

		$this->service->deleteInstance($gateway, 'nonexistent');
	}

	public function testSetDefaultInstanceUpdatesDefaultFlag(): void {
		$gateway = $this->makeGatewayMock('sms', 'SMS', [
			['field' => 'url', 'prompt' => 'URL'],
		]);
		$first = $this->service->createInstance($gateway, 'First', ['url' => 'https://first.example.com']);
		$second = $this->service->createInstance($gateway, 'Second', ['url' => 'https://second.example.com']);

		$this->service->setDefaultInstance($gateway, $second['id']);

		$instances = $this->service->listInstances($gateway);
		$instancesByLabel = array_column($instances, null, 'label');
		$this->assertFalse($instancesByLabel['First']['default']);
		$this->assertTrue($instancesByLabel['Second']['default']);
	}

	public function testSetDefaultInstanceThrowsWhenNotFound(): void {
		$gateway = $this->makeGatewayMock('sms', 'SMS', []);

		$this->expectException(GatewayInstanceNotFoundException::class);

		$this->service->setDefaultInstance($gateway, 'nonexistent');
	}

	public function testListInstancesRepairsGatewayRegistryWhenNoDefaultExists(): void {
		$gateway = $this->makeGatewayMock('telegram', 'Telegram', [
			['field' => 'token', 'prompt' => 'Token'],
		]);

		$first = $this->service->createInstance($gateway, 'First', ['token' => 'tok-1']);
		$second = $this->service->createInstance($gateway, 'Second', ['token' => 'tok-2']);

		$registry = json_decode(
			$this->appConfig->getValueString('twofactor_gateway', 'instances:telegram', '[]'),
			true,
			512,
			JSON_THROW_ON_ERROR,
		);
		$this->assertIsArray($registry);

		$registry = array_map(static function (array $meta): array {
			$meta['default'] = false;
			return $meta;
		}, $registry);

		$this->appConfig->setValueString('twofactor_gateway', 'instances:telegram', json_encode($registry, JSON_THROW_ON_ERROR));

		$instances = $this->service->listInstances($gateway);
		$instancesById = array_column($instances, null, 'id');

		$this->assertTrue($instancesById[$first['id']]['default']);
		$this->assertFalse($instancesById[$second['id']]['default']);
	}

	public function testGetGatewayListReturnsCombinedData(): void {
		$smsGateway = $this->makeGatewayMock('sms', 'SMS', [['field' => 'url', 'prompt' => 'URL']]);
		$telegramGateway = $this->makeGatewayMock('telegram', 'Telegram', [['field' => 'token', 'prompt' => 'Token']]);

		$this->gatewayFactory->method('getFqcnList')->willReturn([
			'OCA\\TwoFactorGateway\\Provider\\Channel\\SMS\\Gateway',
			'OCA\\TwoFactorGateway\\Provider\\Channel\\Telegram\\Gateway',
		]);
		$this->gatewayFactory->method('get')
			->willReturnMap([
				['OCA\\TwoFactorGateway\\Provider\\Channel\\SMS\\Gateway', $smsGateway],
				['OCA\\TwoFactorGateway\\Provider\\Channel\\Telegram\\Gateway', $telegramGateway],
			]);

		$this->service->createInstance($smsGateway, 'SMS Prod', ['url' => 'https://sms.example.com']);

		$list = $this->service->getGatewayList();

		$this->assertCount(2, $list);
		$ids = array_column($list, 'id');
		$this->assertContains('sms', $ids);
		$this->assertContains('telegram', $ids);

		$smsByKey = array_values(array_filter($list, fn ($g) => $g['id'] === 'sms'))[0];
		$this->assertCount(1, $smsByKey['instances']);
	}

	public function testGetGatewayListIncludesProviderCatalogMetadata(): void {
		$whatsAppGateway = $this->makeCatalogGatewayMock(
			'whatsapp',
			'WhatsApp',
			[['field' => 'base_url', 'prompt' => 'Base URL']],
			'provider',
			[[
				'id' => 'gowhatsapp',
				'name' => 'WhatsApp',
				'fields' => [
					['field' => 'base_url', 'prompt' => 'Base URL'],
					['field' => 'device_name', 'prompt' => 'Device name', 'optional' => true],
				],
			]],
		);

		$this->gatewayFactory->method('getFqcnList')->willReturn([
			'OCA\\TwoFactorGateway\\Provider\\Channel\\WhatsApp\\Gateway',
		]);
		$this->gatewayFactory->method('get')
			->willReturnMap([
				['OCA\\TwoFactorGateway\\Provider\\Channel\\WhatsApp\\Gateway', $whatsAppGateway],
			]);

		$list = $this->service->getGatewayList();

		$this->assertCount(1, $list);
		$this->assertSame('provider', $list[0]['providerSelector']['field']);
		$this->assertSame('gowhatsapp', $list[0]['providerCatalog'][0]['id']);
		$this->assertSame('device_name', $list[0]['providerCatalog'][0]['fields'][1]['field']);
	}

	public function testCatalogInstanceRecordLoadsSelectedProviderFields(): void {
		$telegramGateway = $this->makeCatalogGatewayMock(
			'telegram',
			'Telegram',
			[['field' => 'provider', 'prompt' => 'Provider', 'hidden' => true]],
			'provider',
			[[
				'id' => 'telegram_bot',
				'name' => 'Telegram Bot',
				'fields' => [
					['field' => 'token', 'prompt' => 'Bot Token'],
				],
			]],
		);

		$created = $this->service->createInstance($telegramGateway, 'Telegram', [
			'provider' => 'telegram_bot',
		]);
		$this->appConfig->setValueString('twofactor_gateway', 'telegram:' . $created['id'] . ':token', 'abc123');

		$instance = $this->service->getInstance($telegramGateway, $created['id']);

		$this->assertSame('telegram_bot', $instance['config']['provider']);
		$this->assertSame('abc123', $instance['config']['token']);
		$this->assertTrue($instance['isComplete']);
	}

	public function testSingleCatalogProviderDefaultsSelectorAndMarksInstanceComplete(): void {
		$whatsAppGateway = $this->makeCatalogGatewayMock(
			'whatsapp',
			'WhatsApp',
			[['field' => 'base_url', 'prompt' => 'Base URL']],
			'provider',
			[[
				'id' => 'gowhatsapp',
				'name' => 'WhatsApp',
				'fields' => [
					['field' => 'base_url', 'prompt' => 'Base URL', 'optional' => false],
				],
			]],
		);

		$created = $this->service->createInstance($whatsAppGateway, 'Prod', [
			'base_url' => 'https://wa.example.com',
		]);

		$this->assertTrue($created['isComplete']);
		$this->assertSame('gowhatsapp', $created['config']['provider']);
		$this->assertSame('https://wa.example.com', $created['config']['base_url']);
	}

	public function testInstanceIsCompleteWhenAllRequiredFieldsSet(): void {
		$gateway = $this->makeGatewayMock('sms', 'SMS', [
			['field' => 'url', 'prompt' => 'URL', 'optional' => false],
			['field' => 'token', 'prompt' => 'Token', 'optional' => true],
		]);
		$created = $this->service->createInstance($gateway, 'Prod', ['url' => 'https://sms.example.com']);

		$this->assertTrue($created['isComplete']);
	}

	public function testInstanceIsNotCompleteWhenRequiredFieldsMissing(): void {
		$gateway = $this->makeGatewayMock('sms', 'SMS', [
			['field' => 'url', 'prompt' => 'URL', 'optional' => false],
			['field' => 'token', 'prompt' => 'Token', 'optional' => false],
		]);
		$created = $this->service->createInstance($gateway, 'Prod', ['url' => 'https://sms.example.com']);

		$this->assertFalse($created['isComplete']);
	}

	public function testInstanceIdsAreUniqueAcrossMultipleGateways(): void {
		$sms = $this->makeGatewayMock('sms', 'SMS', [['field' => 'url', 'prompt' => 'URL']]);
		$telegram = $this->makeGatewayMock('telegram', 'Telegram', [['field' => 'token', 'prompt' => 'Token']]);

		$a = $this->service->createInstance($sms, 'A', ['url' => 'https://a.example.com']);
		$b = $this->service->createInstance($telegram, 'B', ['token' => 'tok']);

		$this->assertNotSame($a['id'], $b['id']);
	}
}
