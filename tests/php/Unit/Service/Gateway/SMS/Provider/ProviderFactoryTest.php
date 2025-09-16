<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\ClickatellCentral;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\ProviderFactory;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ProviderFactoryTest extends TestCase {
	#[DataProvider('providerGetProvider')]
	public function testGetProvider(string $id, $expected): void {
		$provider = new ProviderFactory(Server::get(ContainerInterface::class));
		$actual = $provider->getProvider($id);
		$this->assertInstanceOf($expected, $actual);
	}

	public static function providerGetProvider(): array {
		return [
			[ClickatellCentral::SCHEMA['id'], ClickatellCentral::class],
		];
	}
}
