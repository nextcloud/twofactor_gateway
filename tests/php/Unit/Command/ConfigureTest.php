<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Command;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Command\Configure;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Factory as SMSFactory;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\Server;
use Symfony\Component\Console\Input\ArrayInput;

class ConfigureTest extends AppTestCase {

	public function testConfigureSmsProviders(): void {
		$this->makeInMemoryAppConfig();
		self::$store = [];

		/** @var \OC\Console\Application */
		$application = Server::get(\OC\Console\Application::class);
		$output = new ConsoleOutputSpy();
		$input = new ArrayInput(['twofactorauth:gateway:configure']);
		// Don't call loadCommands - manually register and run the command instead
		$command = Server::get(Configure::class);
		$application->setAutoExit(false);

		// Find the SMS gateway index
		$gatewayFactory = Server::get(\OCA\TwoFactorGateway\Provider\Gateway\Factory::class);
		$gatewayChoices = [];
		foreach ($gatewayFactory->getFqcnList() as $fqcn) {
			$gateway = $gatewayFactory->get($fqcn);
			$gatewayChoices[] = $gateway->getProviderId();
		}
		$smsGatewayIndex = array_search('sms', $gatewayChoices);
		$this->assertNotFalse($smsGatewayIndex, 'SMS gateway not found in available gateways');

		$factory = new SMSFactory();
		foreach ($factory->getFqcnList() as $index => $fqcn) {
			$gateway = $factory->get($fqcn);

			$gatewaySettings = $gateway->getSettings();
			$this->assertNotEmpty($gatewaySettings->fields, "Provider {$gatewaySettings->name} need to define fields.");
			$inputStream = [(string)$smsGatewayIndex, (string)$index];
			$fields = [];
			foreach ($gatewaySettings->fields as $field) {
				$inputStream[] = 'some_value';
				$fields[] = $gatewaySettings->id . '_' . $field->field;
			}

			$input->setStream(self::createStream($inputStream));
			$exitCode = $command->run($input, $output);
			$this->assertSame(0, $exitCode);
			if ($index === 0) {
				$this->assertStringContainsString('SMS', $output->fetch());
			}
			foreach ($fields as $key) {
				$this->assertArrayHasKey($key, self::$store[Application::APP_ID] ?? [], "Field {$key} of provider {$gatewaySettings->name} was not saved.");
			}
		}
	}

	public function testConfigureCreatesInstanceRegistryEntry(): void {
		$this->makeInMemoryAppConfig();

		/** @var \OC\Console\Application */
		$application = Server::get(\OC\Console\Application::class);
		$output = new ConsoleOutputSpy();
		$input = new ArrayInput(['twofactorauth:gateway:configure']);
		$application->loadCommands($input, $output);
		$application->setAutoExit(false);

		// Locate the SMS gateway choice index
		$gatewayFactory = Server::get(\OCA\TwoFactorGateway\Provider\Gateway\Factory::class);
		$gatewayChoices = [];
		foreach ($gatewayFactory->getFqcnList() as $fqcn) {
			$gatewayChoices[] = $gatewayFactory->get($fqcn)->getProviderId();
		}
		$smsGatewayIndex = array_search('sms', $gatewayChoices);
		$this->assertNotFalse($smsGatewayIndex, 'SMS gateway not found');

		// Pick the first SMS sub-provider, supply values for all its fields
		$factory = new SMSFactory();
		$fqcn = $factory->getFqcnList()[0];
		$provider = $factory->get($fqcn);
		$gatewaySettings = $provider->getSettings();

		$inputStream = [(string)$smsGatewayIndex, '0'];
		foreach ($gatewaySettings->fields as $field) {
			$inputStream[] = 'test_value';
		}

		$input->setStream(self::createStream($inputStream));
		$exitCode = $application->run($input, $output);
		$this->assertSame(0, $exitCode);

		// After configure, a registry entry must exist so the web UI can list the instance
		$registryJson = self::$store[Application::APP_ID]['instances:sms'] ?? null;
		$this->assertNotNull($registryJson, 'instances:sms registry entry must be created after CLI configure');

		$registry = json_decode((string)$registryJson, true);
		$this->assertIsArray($registry);
		$this->assertCount(1, $registry);
		$this->assertSame('Default', $registry[0]['label']);
		$this->assertTrue($registry[0]['default']);
	}
}
