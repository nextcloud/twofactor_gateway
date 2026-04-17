<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\WhatsApp;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Gateway;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\Gateway as GoWhatsAppGateway;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GatewayTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private GoWhatsAppGateway&MockObject $goWhatsAppGateway;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->goWhatsAppGateway = $this->createMock(GoWhatsAppGateway::class);
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
		$this->assertSame(['base_url', 'device_name'], array_map(
			static fn (FieldDefinition $field): string => $field->field,
			$settings->fields,
		));
		$this->assertCount(0, array_filter(
			$settings->fields,
			static fn (FieldDefinition $field): bool => $field->field === 'session_id',
		));
	}

	public function testProviderCatalogExposesOnlyGoWhatsAppAsWhatsApp(): void {
		$this->goWhatsAppGateway
			->method('getSettings')
			->willReturn(new Settings(
				name: 'GoWhatsApp',
				id: 'gowhatsapp',
				fields: [new FieldDefinition(field: 'base_url', prompt: 'Base URL')],
			));
		$this->goWhatsAppGateway
			->method('getProviderId')
			->willReturn('gowhatsapp');

		$gateway = $this->newGateway();
		$catalog = $gateway->getProviderCatalog();

		$this->assertCount(1, $catalog);
		$this->assertSame('gowhatsapp', $catalog[0]['id']);
		$this->assertSame('WhatsApp', $catalog[0]['name']);
	}

	public function testSendDelegatesToGoWhatsAppGateway(): void {
		$this->goWhatsAppGateway->expects($this->once())
			->method('send')
			->with('+55 (11) 99999-9999', 'codigo 123', ['source' => 'test']);

		$gateway = $this->newGateway();
		$gateway->send('+55 (11) 99999-9999', 'codigo 123', ['source' => 'test']);
	}

	public function testCliConfigureDelegatesToGoWhatsAppGateway(): void {
		$input = $this->createMock(InputInterface::class);
		$output = $this->createMock(OutputInterface::class);

		$this->goWhatsAppGateway->expects($this->once())
			->method('cliConfigure')
			->with($input, $output)
			->willReturn(0);

		$gateway = $this->newGateway();
		$this->assertSame(0, $gateway->cliConfigure($input, $output));
	}

	private function newGateway(): Gateway {
		return new Gateway(
			$this->appConfig,
			$this->goWhatsAppGateway,
		);
	}
}
