<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\GatewayInstanceNotFoundException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
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
			fn (array $f) => new FieldDefinition($f['field'], $f['prompt'], $f['default'] ?? '', $f['optional'] ?? false),
			$fieldDefs,
		);
		$settings = new Settings(name: $name, id: $id, fields: $fields);
		$gateway = $this->createMock(IGateway::class);
		$gateway->method('getProviderId')->willReturn($id);
		$gateway->method('getSettings')->willReturn($settings);
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

	public function testFirstInstanceActivatesSyncsToPrimaryKeys(): void {
		$gateway = $this->makeGatewayMock('telegram', 'Telegram', [
			['field' => 'token', 'prompt' => 'Token'],
		]);

		$this->service->createInstance($gateway, 'Main', ['token' => 'mytoken123']);

		$primaryKey = 'telegram_token';
		$this->assertArrayHasKey($primaryKey, self::$store[Application::APP_ID] ?? [], 'Primary key not synced when first instance created');
		$this->assertSame('mytoken123', self::$store[Application::APP_ID][$primaryKey]);
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

	public function testUpdateDefaultInstanceSyncsToPrimaryKeys(): void {
		$gateway = $this->makeGatewayMock('sms', 'SMS', [
			['field' => 'url', 'prompt' => 'URL'],
		]);
		$created = $this->service->createInstance($gateway, 'Main', ['url' => 'https://old.example.com']);

		$this->service->updateInstance($gateway, $created['id'], 'Main', ['url' => 'https://new.example.com']);

		$this->assertSame('https://new.example.com', self::$store[Application::APP_ID]['sms_url'] ?? '');
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

	public function testDeleteDefaultInstanceClearsPrimaryKeys(): void {
		$gateway = $this->makeGatewayMock('telegram', 'Telegram', [
			['field' => 'token', 'prompt' => 'Token'],
		]);
		$created = $this->service->createInstance($gateway, 'Main', ['token' => 'mytoken']);
		$this->assertTrue($created['default']);

		$this->service->deleteInstance($gateway, $created['id']);

		$this->assertArrayNotHasKey('telegram_token', self::$store[Application::APP_ID] ?? []);
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

	public function testSetDefaultInstanceSyncsToPrimaryKeys(): void {
		$gateway = $this->makeGatewayMock('sms', 'SMS', [
			['field' => 'url', 'prompt' => 'URL'],
		]);
		$first = $this->service->createInstance($gateway, 'First', ['url' => 'https://first.example.com']);
		$second = $this->service->createInstance($gateway, 'Second', ['url' => 'https://second.example.com']);

		$this->service->setDefaultInstance($gateway, $second['id']);

		$this->assertSame('https://second.example.com', self::$store[Application::APP_ID]['sms_url'] ?? '');
	}

	public function testSetDefaultInstanceThrowsWhenNotFound(): void {
		$gateway = $this->makeGatewayMock('sms', 'SMS', []);

		$this->expectException(GatewayInstanceNotFoundException::class);

		$this->service->setDefaultInstance($gateway, 'nonexistent');
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
