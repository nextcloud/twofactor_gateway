<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\SMS\Provider;

use OCA\TwoFactorGateway\Provider\Channel\SMS\Factory;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\ClickatellCentral;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class FactoryTest extends TestCase {
	#[DataProvider('providerGetProvider')]
	public function testGetProvider(string $id, $expected): void {
		$provider = new Factory(Server::get(ContainerInterface::class));
		$actual = $provider->getProvider($id);
		$this->assertInstanceOf($expected, $actual);
	}

	public static function providerGetProvider(): array {
		return [
			[ClickatellCentral::SCHEMA['id'], ClickatellCentral::class],
		];
	}
}
