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

class StatusTest extends AppTestCase {

	public function testExecute(): void {
		$store = [];
		$this->replaceAppConfig($store);

		/** @var Application */
		$application = Server::get(Application::class);
		$input = new ArrayInput(['twofactorauth:gateway:status']);
		$output = new ConsoleOutputSpy();
		$application->loadCommands($input, $output);
		$application->setAutoExit(false);

		$exitCode = $application->run($input, $output);

		$this->assertSame(0, $exitCode);
		$this->assertStringContainsString('not configured', $output->fetch());
		$this->assertArrayHasKey('twofactor_gateway', $store);
		foreach ($store['twofactor_gateway'] as $key => $value) {
			$this->assertStringEndsWith('_provider_name', $key);
			$this->assertEmpty($value);
		}
	}
}
