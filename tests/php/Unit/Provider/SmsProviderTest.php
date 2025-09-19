<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider;

use OCA\TwoFactorGateway\Provider\SmsProvider;
use OCA\TwoFactorGateway\Provider\State;
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Gateway;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\ISession;
use OCP\IUser;
use OCP\Security\ISecureRandom;
use OCP\Template\ITemplateManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SmsProviderTest extends TestCase {
	private IGateway&MockObject $smsGateway;
	private StateStorage&MockObject $stateStorage;
	private ISession&MockObject $session;
	private ISecureRandom&MockObject $random;
	private IL10n&MockObject $l10n;
	private IInitialState&MockObject $initialState;
	private ITemplateManager&MockObject $templateManager;
	private SmsProvider $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->smsGateway = $this->createMock(Gateway::class);
		$this->stateStorage = $this->createMock(StateStorage::class);
		$this->session = $this->createMock(ISession::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->templateManager = $this->createMock(ITemplateManager::class);
		$this->initialState = $this->createMock(IInitialState::class);

		$this->provider = new SmsProvider(
			$this->smsGateway,
			$this->stateStorage,
			$this->session,
			$this->random,
			$this->l10n,
			$this->templateManager,
			$this->initialState,
		);
	}

	public function testIsTwoFactorAuthDisabledForUser() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$state = new State($user, SmsProvider::STATE_DISABLED, 'signal');
		$this->stateStorage->expects($this->once())
			->method('get')
			->with($user)
			->willReturn($state);

		$enabled = $this->provider->isTwoFactorAuthEnabledForUser($user);

		$this->assertFalse($enabled);
	}

	public function testIsTwoFactorAuthEnabledForUser() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$state = new State($user, SmsProvider::STATE_ENABLED, 'signal');
		$this->stateStorage->expects($this->once())
			->method('get')
			->with($user)
			->willReturn($state);

		$enabled = $this->provider->isTwoFactorAuthEnabledForUser($user);

		$this->assertTrue($enabled);
	}
}
