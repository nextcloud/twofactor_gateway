<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Service\GatewayAdminScreenService;
use OCA\TwoFactorGateway\Service\GatewayCatalogService;
use OCA\TwoFactorGateway\Service\GatewayPermissionService;
use OCA\TwoFactorGateway\Service\GatewayViewScope;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GatewayAdminScreenServiceTest extends TestCase {
	private GatewayCatalogService&MockObject $gatewayCatalogService;
	private GatewayPermissionService&MockObject $gatewayPermissionService;
	private IGroupManager&MockObject $groupManager;

	protected function setUp(): void {
		parent::setUp();

		$this->gatewayCatalogService = $this->createMock(GatewayCatalogService::class);
		$this->gatewayPermissionService = $this->createMock(GatewayPermissionService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
	}

	public function testBuildReturnsGatewaysGroupsAndFlattenedItems(): void {
		$actor = $this->createMock(IUser::class);
		$ops = $this->createGroup('ops', 'Operations');
		$admins = $this->createGroup('admins', 'Admins');
		$gateways = [
			[
				'id' => 'whatsapp',
				'name' => 'WhatsApp',
				'fields' => [['field' => 'base_url', 'prompt' => 'Base URL']],
				'providerSelector' => ['field' => 'provider', 'prompt' => 'Provider'],
				'providerCatalog' => [
					['id' => 'gowhatsapp', 'name' => 'Go WhatsApp', 'fields' => [['field' => 'base_url', 'prompt' => 'Base URL']]],
					['id' => 'whatsapp', 'name' => 'WhatsApp Cloud', 'fields' => [['field' => 'token', 'prompt' => 'Token']]],
				],
				'instances' => [
					[
						'id' => 'wa-2',
						'label' => 'Beta',
						'default' => false,
						'createdAt' => '2026-01-01T00:00:00+00:00',
						'config' => ['provider' => 'gowhatsapp'],
						'isComplete' => true,
						'groupIds' => ['ops'],
						'priority' => 1,
					],
					[
						'id' => 'wa-1',
						'label' => 'Alpha',
						'default' => true,
						'createdAt' => '2026-01-01T00:00:00+00:00',
						'config' => ['provider' => 'whatsapp'],
						'isComplete' => true,
						'groupIds' => ['admins'],
						'priority' => 3,
					],
				],
			],
		];

		$this->gatewayCatalogService->expects($this->once())
			->method('listGateways')
			->with($actor)
			->willReturn($gateways);

		$this->groupManager->expects($this->once())
			->method('search')
			->with('', 200, 0)
			->willReturn([$ops, $admins]);

		$this->gatewayPermissionService->expects($this->once())
			->method('filterAssignableGroups')
			->with($actor, [$ops, $admins])
			->willReturn([$ops, $admins]);

		$this->gatewayPermissionService->expects($this->once())
			->method('resolveViewScope')
			->with($actor)
			->willReturn(GatewayViewScope::ADMIN);

		$service = new GatewayAdminScreenService(
			$this->gatewayCatalogService,
			$this->gatewayPermissionService,
			$this->groupManager,
		);

		$screen = $service->build($actor);

		$this->assertSame($gateways, $screen['gateways']);
		$this->assertSame([
			['id' => 'admins', 'displayName' => 'Admins'],
			['id' => 'ops', 'displayName' => 'Operations'],
		], $screen['groups']);
		$this->assertSame([
			'canView' => true,
			'canCreateInstances' => true,
			'canEditInstances' => true,
			'canDeleteInstances' => true,
			'canSetDefaultInstances' => true,
			'canManageRouting' => true,
			'canTestInstances' => true,
			'canReorderInstances' => true,
		], $screen['allowedActions']);
		$this->assertCount(2, $screen['items']);
		$this->assertSame('whatsapp:wa-1', $screen['items'][0]['orderKey']);
		$this->assertSame('WhatsApp Cloud', $screen['items'][0]['providerName']);
		$this->assertSame(['Admins'], $screen['items'][0]['groupNames']);
		$this->assertSame('whatsapp:wa-2', $screen['items'][1]['orderKey']);
		$this->assertSame('Go WhatsApp', $screen['items'][1]['providerName']);
		$this->assertSame(['Operations'], $screen['items'][1]['groupNames']);
	}

	public function testBuildDisablesCreateForDelegatedActorsWithoutAssignableGroups(): void {
		$actor = $this->createMock(IUser::class);

		$this->gatewayCatalogService->expects($this->once())
			->method('listGateways')
			->with($actor)
			->willReturn([]);

		$this->groupManager->expects($this->once())
			->method('search')
			->with('', 200, 0)
			->willReturn([]);

		$this->gatewayPermissionService->expects($this->once())
			->method('filterAssignableGroups')
			->with($actor, [])
			->willReturn([]);

		$this->gatewayPermissionService->expects($this->once())
			->method('resolveViewScope')
			->with($actor)
			->willReturn(GatewayViewScope::DELEGATED);

		$service = new GatewayAdminScreenService(
			$this->gatewayCatalogService,
			$this->gatewayPermissionService,
			$this->groupManager,
		);

		$screen = $service->build($actor);

		$this->assertSame([
			'canView' => true,
			'canCreateInstances' => false,
			'canEditInstances' => true,
			'canDeleteInstances' => true,
			'canSetDefaultInstances' => true,
			'canManageRouting' => true,
			'canTestInstances' => true,
			'canReorderInstances' => true,
		], $screen['allowedActions']);
	}

	private function createGroup(string $id, string $displayName): IGroup&MockObject {
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn($id);
		$group->method('getDisplayName')->willReturn($displayName);
		return $group;
	}
}
