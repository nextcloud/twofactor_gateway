<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Command;

use OC\Console\Application;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\Server;
use Symfony\Component\Console\Input\ArrayInput;

class TestTest extends AppTestCase {

	public function testExecuteWithIncompleteConfig(): void {
		$this->makeInMemoryAppConfig();
		self::$store = [];

		/** @var Application */
		$application = Server::get(Application::class);
		$output = new ConsoleOutputSpy();
		$application->loadCommands(new ArrayInput([]), $output);
		$application->setAutoExit(false);

		$factory = new GatewayFactory();
		foreach ($factory->getFqcnList() as $fqcn) {
			$gateway = $factory->get($fqcn);
			$input = new ArrayInput(['twofactorauth:gateway:test', 'gateway' => $gateway->getProviderId(), 'identifier' => 'some_identifier']);
			$exitCode = $application->run($input, $output);
			$this->assertSame(1, $exitCode);
			$this->assertStringContainsString("Gateway {$gateway->getProviderId()} is not configured", $output->fetch());
		}
	}
}
