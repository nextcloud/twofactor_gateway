<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Command;

use OC\Console\Application;
use OCA\TwoFactorGateway\Provider\Gateway\Factory;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\IAppConfig;
use OCP\Server;
use Symfony\Component\Console\Input\ArrayInput;

class RemoveTest extends AppTestCase {

	public function testExecuteWithInvalidProvider(): void {
		$store = [];
		$this->replaceAppConfig($store);

		/** @var Application */
		$application = Server::get(Application::class);
		$input = new ArrayInput(['twofactorauth:gateway:remove']);
		$input->setStream(self::createStream(['99999']));
		$output = new ConsoleOutputSpy();
		$application->loadCommands($input, $output);
		$application->setAutoExit(false);

		$exitCode = $application->run($input, $output);

		$this->assertSame(1, $exitCode);
	}

	public function testExecuteGenericProvidersWithSuccess(): void {
		$store = [];
		$this->replaceAppConfig($store);

		$appConfig = Server::get(IAppConfig::class);

		/** @var Application */
		$application = Server::get(Application::class);

		$factory = new Factory();
		foreach ($factory->getFqcnList() as $index => $fqcn) {
			$providerId = $fqcn::getProviderId();
			$provider = $factory->get($providerId);
			$settings = $provider->getSettings();
			if (count($settings->fields) === 0) {
				// Only generic providers haven't fields at main level
				continue;
			}
			// Simulate a configured provider
			// by setting all its fields to some dummy value
			foreach ($settings->fields as $field) {
				$appConfig->setValueString('twofactor_gateway', $settings->id . '_' . $field->field, 'some_value');
			}

			$currentKeys = fn ($s) => array_filter(array_keys($s['twofactor_gateway']), fn ($k) => str_starts_with($k, $settings->id . '_'));
			$actualSettings = $currentKeys($store);

			$this->assertNotEmpty($actualSettings, 'Provider ' . $settings->name . ' with namespace ' . $fqcn . ' has no settings configured.');
			$this->assertCount(count($settings->fields), $actualSettings, 'Provider ' . $settings->name . ' with namespace ' . $fqcn . ' has not all settings configured.');

			$input = new ArrayInput(['twofactorauth:gateway:remove']);
			$input->setStream(self::createStream([(string)$index]));
			$output = new ConsoleOutputSpy();
			$application->loadCommands($input, $output);
			$application->setAutoExit(false);
			$exitCode = $application->run($input, $output);
			$this->assertSame(0, $exitCode);

			// Check that all settings have been removed
			$actualSettings = $currentKeys($store);
			$this->assertEmpty($actualSettings, 'Provider ' . $settings->name . ' with namespace ' . $fqcn . ' has still settings configured after removal.');
		}
	}
}
