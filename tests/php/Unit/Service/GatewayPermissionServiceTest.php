<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Exception\GatewayPermissionDeniedException;
use OCA\TwoFactorGateway\Service\GatewayPermissionService;
use OCA\TwoFactorGateway\Service\GatewayViewScope;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GatewayPermissionServiceTest extends TestCase {
	private IGroupManager&MockObject $groupManager;
	private ISubAdmin&MockObject $subAdmin;
	private GatewayPermissionService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);
		$this->service = new GatewayPermissionService($this->groupManager, $this->subAdmin);
	}

	public function testResolveViewScopeReturnsAdminForRealAdmins(): void {
		$actor = $this->makeUser('admin');
		$this->groupManager->method('isAdmin')->with('admin')->willReturn(true);
		$this->groupManager->method('isDelegatedAdmin')->with('admin')->willReturn(false);

		$this->assertSame(GatewayViewScope::ADMIN, $this->service->resolveViewScope($actor));
		$this->assertTrue($this->service->canManageRouting($actor, ['groupIds' => []]));
	}

	public function testDelegatedAdminCanOnlyAccessInstancesInsideOwnSubadminGroups(): void {
		$actor = $this->makeUser('delegated');
		$this->groupManager->method('isAdmin')->with('delegated')->willReturn(false);
		$this->groupManager->method('isDelegatedAdmin')->with('delegated')->willReturn(true);
		$this->subAdmin->method('getSubAdminsGroups')->with($actor)->willReturn([
			$this->makeGroup('client-a'),
			$this->makeGroup('client-b'),
		]);

		$this->assertSame(GatewayViewScope::DELEGATED, $this->service->resolveViewScope($actor));
		$this->assertTrue($this->service->canViewInstance($actor, ['groupIds' => ['client-a']]));
		$this->assertFalse($this->service->canViewInstance($actor, ['groupIds' => ['client-a', 'foreign']]));
		$this->assertFalse($this->service->canViewInstance($actor, ['groupIds' => []]));
		$this->assertSame(
			[['groupIds' => ['client-a']]],
			$this->service->filterVisibleInstances($actor, [
				['groupIds' => ['client-a']],
				['groupIds' => ['foreign']],
				['groupIds' => []],
			]),
		);
	}

	public function testDelegatedAdminCreateScopeThrowsExplicitPermissionError(): void {
		$actor = $this->makeUser('delegated');
		$this->groupManager->method('isAdmin')->with('delegated')->willReturn(false);
		$this->groupManager->method('isDelegatedAdmin')->with('delegated')->willReturn(true);
		$this->subAdmin->method('getSubAdminsGroups')->with($actor)->willReturn([
			$this->makeGroup('client-a'),
		]);

		$this->expectException(GatewayPermissionDeniedException::class);
		$this->expectExceptionMessage('outside your group scope');

		$this->service->assertCanCreateInstanceForGroups($actor, ['client-a', 'foreign']);
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
