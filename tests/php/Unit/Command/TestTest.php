<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Command;

use OC\Console\Application;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\Server;
use Symfony\Component\Console\Input\ArrayInput;

class TestTest extends AppTestCase {

	private function createGoWhatsAppGateway(\OCP\IAppConfig $appConfig): \OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\Gateway {
		$clientService = $this->createMock(\OCP\Http\Client\IClientService::class);
		$l10n = $this->createMock(\OCP\IL10N::class);
		$logger = $this->createMock(\Psr\Log\LoggerInterface::class);
		$eventDispatcher = $this->createMock(\OCP\EventDispatcher\IEventDispatcher::class);
			$jobManager = $this->createMock(\OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\Service\GoWhatsAppSessionMonitorJobManager::class);

		return new \OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\Gateway(
			$appConfig,
			$clientService,
			$l10n,
			$logger,
			$eventDispatcher,
			$jobManager,
		);
	}

	public function testGoWhatsAppIsIncompleteWithEmptyConfig(): void {
		$appConfig = $this->makeInMemoryAppConfig();
		self::$store = [];

		$gateway = $this->createGoWhatsAppGateway($appConfig);

		$settings = $gateway->getSettings();
		$this->assertNotNull($settings);

		$requiredFields = [];
		foreach ($settings->fields as $field) {
			if (!$field->optional) {
				$requiredFields[] = $field->field;
			}
		}

		$this->assertNotEmpty($requiredFields, 'GoWhatsApp should have required fields');

		$isComplete = $gateway->isComplete();
		$this->assertFalse($isComplete, 'GoWhatsApp isComplete should be false with empty configuration. Required fields are: ' . implode(', ', $requiredFields));
	}

	public function testGoWhatsAppIsCompleteWithRequiredConfig(): void {
		$appConfig = $this->makeInMemoryAppConfig();
		self::$store = [];

		$appConfig->setValueString('twofactor_gateway', 'gowhatsapp_base_url', 'http://localhost:3000');
		$appConfig->setValueString('twofactor_gateway', 'gowhatsapp_username', 'admin');
		$appConfig->setValueString('twofactor_gateway', 'gowhatsapp_password', 'password123');
		$appConfig->setValueString('twofactor_gateway', 'gowhatsapp_phone', '+1234567890');

		$gateway = $this->createGoWhatsAppGateway($appConfig);
		$isComplete = $gateway->isComplete();
		$this->assertTrue(
			$isComplete,
			"Gateway {$gateway->getProviderId()} should be complete when all required fields are configured"
		);
	}

	public function testExecuteWithIncompleteConfig(): void {
		$this->markTestSkipped('Requires proper mocking of OCP\\Server container');
		$this->makeInMemoryAppConfig();
		self::$store = [];

		/** @var Application */
		$application = Server::get(Application::class);
		$output = new ConsoleOutputSpy();
		$application->loadCommands(new ArrayInput([]), $output);
		$application->setAutoExit(false);

		$gateway = $this->createGoWhatsAppGateway($this->makeInMemoryAppConfig());

		$input = new ArrayInput(['twofactorauth:gateway:test', 'gateway' => $gateway->getProviderId(), 'identifier' => 'some_identifier']);
		$exitCode = $application->run($input, $output);
		$this->assertSame(1, $exitCode, "Gateway {$gateway->getProviderId()} should return exit code 1 when not configured");
		$this->assertStringContainsString("Gateway {$gateway->getProviderId()} is not configured", $output->fetch());
	}
}
