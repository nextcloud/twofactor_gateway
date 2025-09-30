<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Command;

use OCA\TwoFactorGateway\AppInfo\Application;
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
		$application->loadCommands($input, $output);
		$application->setAutoExit(false);

		$factory = new SMSFactory();
		foreach ($factory->getFqcnList() as $index => $fqcn) {
			$gateway = $factory->get($fqcn);

			$gatewaySettings = $gateway->getSettings();
			$this->assertNotEmpty($gatewaySettings->fields, "Provider {$gatewaySettings->name} need to define fields.");
			$inputStream = ['0', (string)$index];
			$fields = [];
			foreach ($gatewaySettings->fields as $field) {
				$inputStream[] = 'some_value';
				$fields[] = $gatewaySettings->id . '_' . $field->field;
			}

			$input->setStream(self::createStream($inputStream));
			$exitCode = $application->run($input, $output);
			$this->assertSame(0, $exitCode);
			foreach ($fields as $key) {
				$this->assertArrayHasKey($key, self::$store[Application::APP_ID] ?? [], "Field {$key} of provider {$gatewaySettings->name} was not saved.");
			}
		}
	}
}
