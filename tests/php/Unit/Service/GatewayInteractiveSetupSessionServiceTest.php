<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\GatewayPermissionDeniedException;
use OCA\TwoFactorGateway\Service\GatewayInteractiveSetupSessionService;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\IAppConfig;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;

class GatewayInteractiveSetupSessionServiceTest extends AppTestCase {
	private IAppConfig $appConfig;
	private GatewayInteractiveSetupSessionService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->makeInMemoryAppConfig();
		$this->service = new GatewayInteractiveSetupSessionService($this->appConfig);
	}

	public function testClaimAndAssertCanAccessAllowSameActor(): void {
		$actor = $this->makeUser('alice');

		$this->service->claim($actor, 'gowhatsapp', 'session-1');
		$this->service->assertCanAccess($actor, 'gowhatsapp', 'session-1');

		$this->assertTrue($this->appConfig->hasKey(
			Application::APP_ID,
			'interactive_setup_session_owner:gowhatsapp:session-1',
		));
	}

	public function testAssertCanAccessThrowsWhenSessionBelongsToAnotherActor(): void {
		$this->service->claim($this->makeUser('alice'), 'gowhatsapp', 'session-1');

		$this->expectException(GatewayPermissionDeniedException::class);
		$this->expectExceptionMessage('You are not allowed to access this interactive setup session.');

		$this->service->assertCanAccess($this->makeUser('bob'), 'gowhatsapp', 'session-1');
	}

	public function testAssertCanAccessAllowsUntrackedSessionForBackwardCompatibility(): void {
		$this->service->assertCanAccess($this->makeUser('alice'), 'gowhatsapp', 'session-untracked');
		$this->addToAssertionCount(1);
	}

	public function testReleaseDeletesClaimedSessionOwnership(): void {
		$this->service->claim($this->makeUser('alice'), 'gowhatsapp', 'session-1');
		$this->service->release('gowhatsapp', 'session-1');

		$this->assertFalse($this->appConfig->hasKey(
			Application::APP_ID,
			'interactive_setup_session_owner:gowhatsapp:session-1',
		));
	}

	private function makeUser(string $uid): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}
}
