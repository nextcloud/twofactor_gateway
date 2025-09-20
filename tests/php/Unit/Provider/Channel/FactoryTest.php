<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\TwoFactorGateway\Tests\Unit\Channel\Provider\Gateway;

use OCA\TwoFactorGateway\Provider\Gateway\Factory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FactoryTest extends TestCase {
	#[DataProvider('providerGetGateway')]
	public function testGetGateway(string $name, ?string $expectedFqcn): void {
		$factory = new Factory();

		if ($expectedFqcn === null) {
			$this->expectException(\Exception::class);
			$factory->getGateway($name);
			return;
		}

		$obj = $factory->getGateway($name);
		$this->assertInstanceOf($expectedFqcn, $obj);
		$this->assertInstanceOf(IGateway::class, $obj);
	}

	public static function providerGetGateway(): array {
		$factoryFile = (new \ReflectionClass(Factory::class))->getFileName();
		$baseDir = dirname($factoryFile);

		$cases = [];
		foreach (glob($baseDir . '/*/Gateway.php') ?: [] as $file) {
			$type = basename(dirname($file));
			$fqcn = "OCA\\TwoFactorGateway\\Service\\Gateway\\{$type}\\Gateway";
			$cases["ok-{$type}"] = [strtolower($type), $fqcn];
		}

		$cases['invalid-wrong'] = ['wrong', null];
		return $cases;
	}
}
