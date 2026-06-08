<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Service\GatewayAdminInitialStateService;
use OCA\TwoFactorGateway\Service\GatewayAdminScreenService;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GatewayAdminInitialStateServiceTest extends TestCase {
	private GatewayAdminScreenService&MockObject $gatewayAdminScreenService;
	private IUserSession&MockObject $userSession;

	protected function setUp(): void {
		parent::setUp();

		$this->gatewayAdminScreenService = $this->createMock(GatewayAdminScreenService::class);
		$this->userSession = $this->createMock(IUserSession::class);
	}

	public function testBuildDelegatesToScreenServiceWithCurrentActor(): void {
		$actor = $this->createMock(IUser::class);
		$screen = [
			'gateways' => [],
			'groups' => [],
			'allowedActions' => [
				'canView' => true,
				'canCreateInstances' => true,
				'canEditInstances' => true,
				'canDeleteInstances' => true,
				'canSetDefaultInstances' => true,
				'canManageRouting' => true,
				'canTestInstances' => true,
				'canReorderInstances' => true,
			],
			'items' => [],
		];

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($actor);

		$this->gatewayAdminScreenService->expects($this->once())
			->method('build')
			->with($actor, 200)
			->willReturn($screen);

		$service = new GatewayAdminInitialStateService(
			$this->gatewayAdminScreenService,
			$this->userSession,
		);

		$this->assertSame($screen, $service->build());
	}
}
