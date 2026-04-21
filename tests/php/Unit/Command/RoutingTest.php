<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Command;

use OCA\TwoFactorGateway\Command\Routing;
use OCA\TwoFactorGateway\Provider\Gateway\Factory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\GatewayConfigService;
use OCP\IGroupManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class RoutingTest extends TestCase {
	/** @var Factory&MockObject */
	private Factory $gatewayFactory;
	/** @var GatewayConfigService&MockObject */
	private GatewayConfigService $configService;
	/** @var IGroupManager&MockObject */
	private IGroupManager $groupManager;
	/** @var IGateway&MockObject */
	private IGateway $gateway;

	protected function setUp(): void {
		parent::setUp();

		$this->gatewayFactory = $this->createMock(Factory::class);
		$this->configService = $this->createMock(GatewayConfigService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->gateway = $this->createMock(IGateway::class);

		$this->gatewayFactory
			->method('getFqcnList')
			->willReturn(['gateway-fqcn']);
		$this->gatewayFactory
			->method('get')
			->with('gateway-fqcn')
			->willReturn($this->gateway);
		$this->gateway
			->method('getProviderId')
			->willReturn('sms');
	}

	/**
	 * @dataProvider invalidPriorityProvider
	 */
	public function testRejectsNonIntegerPriorityValues(string $priority): void {
		$this->configService
			->method('listInstances')
			->with($this->gateway)
			->willReturn([[
				'id' => 'instance-1',
				'label' => 'Default',
				'default' => true,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => [],
				'isComplete' => true,
				'groupIds' => [],
				'priority' => 0,
			]]);
		$this->configService
			->expects($this->never())
			->method('updateInstance');
		$this->groupManager
			->expects($this->never())
			->method('search');

		$command = new Routing($this->gatewayFactory, $this->configService, $this->groupManager);
		$input = new ArrayInput([
			'gateway' => 'sms',
			'instance' => 'instance-1',
			'--priority' => $priority,
		]);
		$output = new BufferedOutput();

		$exitCode = $command->run($input, $output);

		$this->assertSame(Command::FAILURE, $exitCode);
		$this->assertStringContainsString('Priority must be an integer.', $output->fetch());
	}

	/**
	 * @return list<array{0: string}>
	 */
	public static function invalidPriorityProvider(): array {
		return [
			['1e2'],
			['1.5'],
			['abc'],
			['+ 1'],
			['0x10'],
		];
	}
}
