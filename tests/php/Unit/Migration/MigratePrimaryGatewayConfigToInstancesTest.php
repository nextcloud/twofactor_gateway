<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Migration;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Migration\Version4000Date20260414093000;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Service\GatewayConfigService;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;

class MigratePrimaryGatewayConfigToInstancesTest extends AppTestCase {
	private GatewayFactory&MockObject $gatewayFactory;
	private GatewayConfigService $service;

	protected function setUp(): void {
		parent::setUp();
		$appConfig = $this->makeInMemoryAppConfig();
		$this->gatewayFactory = $this->createMock(GatewayFactory::class);
		$this->service = new GatewayConfigService($appConfig, $this->gatewayFactory);
	}

	private function makeGatewayMock(string $id, array $fieldDefs): IGateway&MockObject {
		$fields = array_map(
			fn (array $field) => new FieldDefinition($field['field'], $field['prompt'], $field['default'] ?? '', $field['optional'] ?? false, $field['type'] ?? null),
			$fieldDefs,
		);
		$settings = new Settings(name: strtoupper($id), id: $id, fields: $fields);
		$gateway = $this->createMock(IGateway::class);
		$gateway->method('getProviderId')->willReturn($id);
		$gateway->method('getSettings')->willReturn($settings);
		return $gateway;
	}

	public function testRunMigratesConfiguredGatewaysToDefaultInstances(): void {
		$telegramGateway = $this->makeGatewayMock('telegram', [
			['field' => 'token', 'prompt' => 'Token'],
		]);
		$telegramGateway->method('isComplete')->willReturn(true);
		$telegramGateway->method('getConfiguration')->willReturn([
			'token' => 'bot-token',
		]);

		$smsGateway = $this->makeGatewayMock('sms', [
			['field' => 'url', 'prompt' => 'URL'],
		]);
		$smsGateway->method('isComplete')->willReturn(false);
		$smsGateway->method('getConfiguration')->willReturn([]);

		$this->gatewayFactory->method('getFqcnList')->willReturn([
			'TelegramGateway',
			'SmsGateway',
		]);
		$this->gatewayFactory->method('get')->willReturnMap([
			['TelegramGateway', $telegramGateway],
			['SmsGateway', $smsGateway],
		]);

		self::$store[Application::APP_ID]['telegram_token'] = 'bot-token';

		$output = $this->createMock(IOutput::class);
		$output->expects($this->once())
			->method('info')
			->with('Created default gateway instance from primary configuration for telegram');

		$step = new Version4000Date20260414093000($this->gatewayFactory, $this->service);
		$schema = $this->createStub(ISchemaWrapper::class);
		$step->postSchemaChange($output, static fn () => $schema, []);

		$instances = $this->service->listInstances($telegramGateway);
		$this->assertCount(1, $instances);
		$this->assertSame('Default', $instances[0]['label']);
		$this->assertSame('bot-token', $instances[0]['config']['token']);
		$this->assertArrayNotHasKey('instances:sms', self::$store[Application::APP_ID] ?? []);
	}
}
