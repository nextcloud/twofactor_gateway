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
	 * @dataProvider successfulRoutingUpdateProvider
	 *
	 * @param list<string> $availableGroupIds
	 * @param list<string> $expectedGroupIds
	 */
	public function testUpdatesRoutingWithProvidedOptions(string $priority, string $groups, array $availableGroupIds, array $expectedGroupIds): void {
		$instance = [
			'id' => 'instance-1',
			'label' => 'Default',
			'default' => true,
			'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['base_url' => 'https://sms.example.com'],
			'isComplete' => true,
			'groupIds' => ['existing'],
			'priority' => 5,
		];

		$this->configService
			->method('listInstances')
			->with($this->gateway)
			->willReturn([$instance]);
		$this->groupManager
			->method('search')
			->with('')
			->willReturn(array_map([$this, 'makeGroupStub'], $availableGroupIds));
		$this->configService
			->expects($this->once())
			->method('updateInstance')
			->with(
				$this->gateway,
				'instance-1',
				'Default',
				['base_url' => 'https://sms.example.com'],
				$expectedGroupIds,
				(int)$priority,
			)
			->willReturn([
				...$instance,
				'groupIds' => $expectedGroupIds,
				'priority' => (int)$priority,
			]);

		$command = new Routing($this->gatewayFactory, $this->configService, $this->groupManager);
		$input = new ArrayInput([
			'gateway' => 'sms',
			'instance' => 'instance-1',
			'--priority' => $priority,
			'--groups' => $groups,
		]);
		$output = new BufferedOutput();

		$exitCode = $command->run($input, $output);

		$this->assertSame(Command::SUCCESS, $exitCode);
		$this->assertStringContainsString('Routing updated for instance', $output->fetch());
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
	 * @dataProvider invalidGroupsProvider
	 */
	public function testRejectsUnknownGroups(string $groups, string $expectedUnknownGroups): void {
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
		$this->groupManager
			->method('search')
			->with('')
			->willReturn([
				$this->makeGroupStub('admins'),
				$this->makeGroupStub('staff'),
			]);
		$this->configService
			->expects($this->never())
			->method('updateInstance');

		$command = new Routing($this->gatewayFactory, $this->configService, $this->groupManager);
		$input = new ArrayInput([
			'gateway' => 'sms',
			'instance' => 'instance-1',
			'--priority' => '1',
			'--groups' => $groups,
		]);
		$output = new BufferedOutput();

		$exitCode = $command->run($input, $output);

		$this->assertSame(Command::FAILURE, $exitCode);
		$this->assertStringContainsString('Unknown group(s): ' . $expectedUnknownGroups, $output->fetch());
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

	/**
	 * @return list<array{0: string, 1: string, 2: list<string>, 3: list<string>}>
	 */
	public static function successfulRoutingUpdateProvider(): array {
		return [
			['0', '', ['admins', 'staff'], []],
			['10', 'admins', ['admins', 'staff'], ['admins']],
			['25', ' admins , staff ', ['admins', 'staff'], ['admins', 'staff']],
			['-5', 'staff,,', ['admins', 'staff'], ['staff']],
		];
	}

	/**
	 * @return list<array{0: string, 1: string}>
	 */
	public static function invalidGroupsProvider(): array {
		return [
			['ghost', 'ghost'],
			['admins, ghost', 'ghost'],
			['ghost, phantom', 'ghost, phantom'],
		];
	}

	private function makeGroupStub(string $groupId): object {
		return new class($groupId) {
			public function __construct(
				private string $groupId,
			) {
			}

			public function getGID(): string {
				return $this->groupId;
			}
		};
	}
}
