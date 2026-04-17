<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Command;

use OC\Console\Application;
use OCA\TwoFactorGateway\AppInfo\Application as TwoFactorGatewayApplication;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\Server;
use Symfony\Component\Console\Input\ArrayInput;

class StatusTest extends AppTestCase {

	public function testExecute(): void {
		$this->makeInMemoryAppConfig();

		/** @var Application */
		$application = Server::get(Application::class);
		$input = new ArrayInput(['twofactorauth:gateway:status']);
		$output = new ConsoleOutputSpy();
		$application->loadCommands($input, $output);
		$application->setAutoExit(false);

		$exitCode = $application->run($input, $output);

		$this->assertSame(0, $exitCode);
		$this->assertStringContainsString('not configured', $output->fetch());
		if (isset(self::$store['twofactor_gateway'])) {
			foreach (self::$store['twofactor_gateway'] as $key => $value) {
				$this->assertStringEndsWith('_provider_name', $key);
				$this->assertEmpty($value);
			}
		}
	}

	public function testExecuteVerboseShowsInstanceRegistryData(): void {
		$this->makeInMemoryAppConfig();

		self::$store[TwoFactorGatewayApplication::APP_ID]['instances:signal'] = json_encode([
			[
				'id' => 'signal-instance-1',
				'label' => 'Signal primary',
				'default' => true,
				'createdAt' => '2026-04-17T00:00:00+00:00',
				'groupIds' => ['admins'],
				'priority' => 10,
			],
		], JSON_THROW_ON_ERROR);
		self::$store[TwoFactorGatewayApplication::APP_ID]['signal:signal-instance-1:url'] = 'http://localhost:5000';
		self::$store[TwoFactorGatewayApplication::APP_ID]['signal:signal-instance-1:account'] = 'test-account';

		/** @var Application */
		$application = Server::get(Application::class);
		$input = new ArrayInput([
			'twofactorauth:gateway:status',
			'-v' => true,
		]);
		$output = new ConsoleOutputSpy();
		$application->loadCommands($input, $output);
		$application->setAutoExit(false);

		$exitCode = $application->run($input, $output);
		$stdout = $output->fetch();

		$this->assertSame(0, $exitCode);
		$this->assertStringContainsString('Signal: configured', $stdout);
		$this->assertStringContainsString('"instances"', $stdout);
		$this->assertStringContainsString('"signal-instance-1"', $stdout);
	}
}
