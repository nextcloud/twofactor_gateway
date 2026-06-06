<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\FieldExposure;
use OCA\TwoFactorGateway\Provider\FieldType;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Service\GatewayCatalogService;
use OCA\TwoFactorGateway\Service\GatewayConfigService;
use OCA\TwoFactorGateway\Service\GatewayFieldSanitizer;
use OCA\TwoFactorGateway\Service\GatewayInstanceViewFactory;
use OCA\TwoFactorGateway\Service\GatewayPermissionService;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GatewayCatalogServiceTest extends TestCase {
	private GatewayFactory&MockObject $gatewayFactory;
	private GatewayConfigService&MockObject $configService;
	private IGroupManager&MockObject $groupManager;
	private ISubAdmin&MockObject $subAdmin;
	private GatewayCatalogService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->gatewayFactory = $this->createMock(GatewayFactory::class);
		$this->configService = $this->createMock(GatewayConfigService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);
		$this->service = new GatewayCatalogService(
			$this->gatewayFactory,
			$this->configService,
			new GatewayInstanceViewFactory(new GatewayFieldSanitizer()),
			new GatewayPermissionService($this->groupManager, $this->subAdmin),
		);
	}

	public function testListGatewaysKeepsAdminCompatibleCatalogView(): void {
		$actor = $this->makeUser('admin');
		$this->groupManager->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => $uid === 'admin');
		$this->groupManager->method('isDelegatedAdmin')->willReturn(false);

		$gateway = $this->makeGatewayMock('sms', [
			new FieldDefinition(field: 'display_name', prompt: 'Display name', exposure: FieldExposure::DELEGATED),
			new FieldDefinition(field: 'api_token', prompt: 'API token', type: FieldType::SECRET),
		]);
		$this->gatewayFactory->method('getFqcnList')->willReturn(['sms']);
		$this->gatewayFactory->method('get')->with('sms')->willReturn($gateway);
		$this->configService->method('listInstances')->with($gateway)->willReturn([[
			'id' => 'inst-a',
			'label' => 'Primary',
			'default' => true,
			'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['display_name' => 'Primary', 'api_token' => 'secret'],
			'isComplete' => true,
			'groupIds' => ['admins'],
			'priority' => 10,
		]]);

		$list = $this->service->listGateways($actor);

		$this->assertCount(1, $list);
		$this->assertSame(['display_name', 'api_token'], array_map(static fn (array $field): string => $field['field'], $list[0]['fields']));
		$this->assertSame([
			'display_name' => 'Primary',
			'api_token' => 'secret',
		], $list[0]['instances'][0]['config']);
	}

	public function testListGatewaysFiltersInstancesAndSecretsForDelegatedActors(): void {
		$actor = $this->makeUser('delegated');
		$this->groupManager->method('isAdmin')->willReturn(false);
		$this->groupManager->method('isDelegatedAdmin')->willReturnCallback(static fn (string $uid): bool => $uid === 'delegated');
		$this->subAdmin->method('getSubAdminsGroups')->with($actor)->willReturn([
			$this->makeGroup('client-a'),
		]);

		$gateway = $this->makeGatewayMock('sms', [
			new FieldDefinition(field: 'display_name', prompt: 'Display name', exposure: FieldExposure::DELEGATED),
			new FieldDefinition(field: 'api_token', prompt: 'API token', type: FieldType::SECRET, exposure: FieldExposure::DELEGATED),
		]);
		$this->gatewayFactory->method('getFqcnList')->willReturn(['sms']);
		$this->gatewayFactory->method('get')->with('sms')->willReturn($gateway);
		$this->configService->method('listInstances')->with($gateway)->willReturn([
			[
				'id' => 'inst-a',
				'label' => 'Client A',
				'default' => true,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['display_name' => 'Client A', 'api_token' => 'secret-a'],
				'isComplete' => true,
				'groupIds' => ['client-a'],
				'priority' => 10,
			],
			[
				'id' => 'inst-b',
				'label' => 'Foreign',
				'default' => false,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['display_name' => 'Foreign', 'api_token' => 'secret-b'],
				'isComplete' => true,
				'groupIds' => ['foreign'],
				'priority' => 5,
			],
			[
				'id' => 'inst-c',
				'label' => 'Global',
				'default' => false,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['display_name' => 'Global', 'api_token' => 'secret-c'],
				'isComplete' => true,
				'groupIds' => [],
				'priority' => 1,
			],
		]);

		$list = $this->service->listGateways($actor);

		$this->assertCount(1, $list);
		$this->assertSame(['display_name', 'api_token'], array_map(static fn (array $field): string => $field['field'], $list[0]['fields']));
		$this->assertCount(1, $list[0]['instances']);
		$this->assertSame('inst-a', $list[0]['instances'][0]['id']);
		$this->assertSame(['display_name' => 'Client A'], $list[0]['instances'][0]['config']);
	}

	public function testCreateInstanceViewUsesActorScopeForAdminResponses(): void {
		$actor = $this->makeUser('admin');
		$this->groupManager->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => $uid === 'admin');
		$this->groupManager->method('isDelegatedAdmin')->willReturn(false);

		$gateway = $this->makeGatewayMock('signal', [
			new FieldDefinition(field: 'base_url', prompt: 'Base URL', exposure: FieldExposure::DELEGATED),
			new FieldDefinition(field: 'api_token', prompt: 'API token', type: FieldType::SECRET),
		]);

		$view = $this->service->createInstanceView($actor, $gateway, [
			'id' => 'inst-signal',
			'label' => 'Signal',
			'default' => false,
			'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['base_url' => 'https://signal.example.com', 'api_token' => 'secret'],
			'isComplete' => true,
			'groupIds' => ['ops'],
			'priority' => 3,
		]);

		$this->assertSame([
			'base_url' => 'https://signal.example.com',
			'api_token' => 'secret',
		], $view['config']);
		$this->assertSame(['ops'], $view['groupIds']);
		$this->assertSame(3, $view['priority']);
	}

	/**
	 * @param list<FieldDefinition> $fields
	 * @return IGateway&MockObject
	 */
	private function makeGatewayMock(string $id, array $fields): IGateway&MockObject {
		$settings = new Settings(name: ucfirst($id), id: $id, fields: $fields);
		$gateway = $this->createMock(IGateway::class);
		$gateway->method('getProviderId')->willReturn($id);
		$gateway->method('getSettings')->willReturn($settings);
		return $gateway;
	}

	private function makeUser(string $uid): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}

	private function makeGroup(string $gid): IGroup&MockObject {
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn($gid);
		return $group;
	}
}
