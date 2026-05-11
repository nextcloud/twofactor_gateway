<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\WhatsApp;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Factory;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Gateway;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\Gateway as GoWhatsAppGateway;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness\Gateway as WhatsAppBusinessGateway;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GatewayTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private Factory&MockObject $whatsAppFactory;
	private GoWhatsAppGateway&MockObject $goWhatsAppGateway;
	private WhatsAppBusinessGateway&MockObject $whatsAppBusinessGateway;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->whatsAppFactory = $this->createMock(Factory::class);
		$this->goWhatsAppGateway = $this->createMock(GoWhatsAppGateway::class);
		$this->whatsAppBusinessGateway = $this->createMock(WhatsAppBusinessGateway::class);

		$this->whatsAppFactory->method('get')->willReturnMap([
			['gowhatsapp', $this->goWhatsAppGateway],
			['whatsappbusiness', $this->whatsAppBusinessGateway],
			['OCA\\TwoFactorGateway\\Provider\\Channel\\WhatsApp\\Provider\\Drivers\\GoWhatsApp\\Gateway', $this->goWhatsAppGateway],
			['OCA\\TwoFactorGateway\\Provider\\Channel\\WhatsApp\\Provider\\Drivers\\WhatsAppBusiness\\Gateway', $this->whatsAppBusinessGateway],
		]);
	}

	public function testCreateSettingsReusesGoWhatsAppFieldsWithoutLegacySessionMetadata(): void {
		$this->goWhatsAppGateway
			->method('getSettings')
			->willReturn(new Settings(
				name: 'GoWhatsApp',
				allowMarkdown: true,
				instructions: 'Driver instructions',
				fields: [
					new FieldDefinition(field: 'base_url', prompt: 'Base URL'),
					new FieldDefinition(field: 'device_name', prompt: 'Device name'),
				],
			));

		$gateway = $this->newGateway();
		$settings = $gateway->getSettings();

		$this->assertSame('WhatsApp', $settings->name);
		$this->assertTrue($settings->allowMarkdown);
		$this->assertSame('Driver instructions', $settings->instructions);
		$this->assertSame(['provider', 'base_url', 'device_name'], array_map(
			static fn (FieldDefinition $field): string => $field->field,
			$settings->fields,
		));
		$this->assertCount(0, array_filter(
			$settings->fields,
			static fn (FieldDefinition $field): bool => $field->field === 'session_id',
		));
	}

	public function testProviderCatalogExposesGoWhatsAppAndWhatsAppBusiness(): void {
		$this->whatsAppFactory
			->method('getFqcnList')
			->willReturn([
				'OCA\\TwoFactorGateway\\Provider\\Channel\\WhatsApp\\Provider\\Drivers\\GoWhatsApp\\Gateway',
				'OCA\\TwoFactorGateway\\Provider\\Channel\\WhatsApp\\Provider\\Drivers\\WhatsAppBusiness\\Gateway',
			]);

		$this->goWhatsAppGateway
			->method('getSettings')
			->willReturn(new Settings(
				name: 'WhatsApp web',
				id: 'gowhatsapp',
				fields: [new FieldDefinition(field: 'base_url', prompt: 'Base URL')],
			));
		$this->whatsAppBusinessGateway
			->method('getSettings')
			->willReturn(new Settings(
				name: 'WhatsApp Business',
				id: 'whatsappbusiness',
				fields: [new FieldDefinition(field: 'phone_number_id', prompt: 'Phone number ID')],
			));

		$gateway = $this->newGateway();
		$catalog = $gateway->getProviderCatalog();

		$this->assertCount(2, $catalog);
		$this->assertSame('gowhatsapp', $catalog[0]['id']);
		$this->assertSame('WhatsApp web', $catalog[0]['name']);
		$this->assertSame('whatsappbusiness', $catalog[1]['id']);
		$this->assertSame('WhatsApp Business', $catalog[1]['name']);
	}

	public function testSendDelegatesToGoWhatsAppGateway(): void {
		$this->whatsAppFactory->expects($this->once())
			->method('get')
			->with('gowhatsapp')
			->willReturn($this->goWhatsAppGateway);

		$this->goWhatsAppGateway->expects($this->once())
			->method('send')
			->with('+55 (11) 99999-9999', 'codigo 123', ['source' => 'test']);

		$gateway = $this->newGateway();
		$gateway->send('+55 (11) 99999-9999', 'codigo 123', ['source' => 'test']);
	}

	public function testCliConfigureDelegatesToGoWhatsAppGateway(): void {
		$input = $this->createMock(InputInterface::class);
		$output = $this->createMock(OutputInterface::class);
		$this->whatsAppFactory->expects($this->once())
			->method('get')
			->with('gowhatsapp')
			->willReturn($this->goWhatsAppGateway);

		$this->goWhatsAppGateway->expects($this->once())
			->method('cliConfigure')
			->with($input, $output)
			->willReturn(0);

		$gateway = $this->newGateway();
		$this->assertSame(0, $gateway->cliConfigure($input, $output));
	}

	public function testSyncAfterConfigurationChangeDelegatesToGoWhatsAppGateway(): void {
		$this->whatsAppFactory->expects($this->once())
			->method('get')
			->with('gowhatsapp')
			->willReturn($this->goWhatsAppGateway);

		$this->goWhatsAppGateway->expects($this->once())
			->method('syncAfterConfigurationChange');

		$gateway = $this->newGateway();
		$gateway->syncAfterConfigurationChange();
	}

	public function testSendDelegatesToWhatsAppBusinessGatewayWhenProviderIsConfigured(): void {
		$this->goWhatsAppGateway->expects($this->never())->method('send');
		$this->whatsAppFactory->expects($this->once())
			->method('get')
			->with('whatsappbusiness')
			->willReturn($this->whatsAppBusinessGateway);

		$this->whatsAppBusinessGateway->expects($this->once())
			->method('withRuntimeConfig')
			->willReturn($this->whatsAppBusinessGateway);
		$this->whatsAppBusinessGateway->expects($this->once())
			->method('send')
			->with('+55 (11) 99999-9999', 'codigo 123', ['provider' => 'whatsappbusiness']);

		$gateway = $this->newGateway()->withRuntimeConfig(['provider' => 'whatsappbusiness']);
		$gateway->send('+55 (11) 99999-9999', 'codigo 123', ['provider' => 'whatsappbusiness']);
	}

	private function newGateway(): Gateway {
		return new Gateway(
			$this->appConfig,
			$this->whatsAppFactory,
		);
	}
}
