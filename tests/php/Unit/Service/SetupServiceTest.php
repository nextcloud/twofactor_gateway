<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use Exception;
use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Exception\VerificationException;
use OCA\TwoFactorGateway\Provider\AProvider;
use OCA\TwoFactorGateway\Provider\Factory as ProviderFactory;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\State;
use OCA\TwoFactorGateway\Service\SetupService;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\IL10N;
use OCP\IUser;
use OCP\L10N\IFactory as IL10NFactory;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SetupServiceTest extends TestCase {
	private StateStorage&MockObject $stateStorage;
	private GatewayFactory&MockObject $gatewayFactory;
	private ISecureRandom&MockObject $random;
	private ProviderFactory&MockObject $providerFactory;
	private IRegistry&MockObject $registry;
	private SetupService $setupService;
	private IL10N $l10n;

	protected function setUp(): void {
		parent::setUp();

		$this->stateStorage = $this->createMock(StateStorage::class);
		$this->gatewayFactory = $this->createMock(GatewayFactory::class);
		$this->providerFactory = $this->createMock(ProviderFactory::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->registry = $this->createMock(IRegistry::class);
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);

		$this->setupService = new SetupService(
			$this->stateStorage,
			$this->gatewayFactory,
			$this->providerFactory,
			$this->random,
			$this->registry,
			$this->l10n,
		);
	}

	public function testStartSetupTransmissionError() {
		$identifier = '1234';
		$user = $this->createMock(IUser::class);
		$gateway = $this->createMock(IGateway::class);
		$this->gatewayFactory->expects($this->once())
			->method('get')
			->with('sms')
			->willReturn($gateway);
		$gateway->expects($this->once())
			->method('send')
			->willThrowException(new VerificationException());
		$this->expectException(VerificationException::class);

		$this->setupService->startSetup($user, 'sms', $identifier);
	}

	public function testStartSetup() {
		$identifier = '0123456789';
		$gatewayName = 'signal';
		$gateway = $this->createMock(IGateway::class);
		$this->gatewayFactory->expects($this->once())
			->method('get')
			->with($gatewayName)
			->willReturn($gateway);
		$user = $this->createMock(IUser::class);
		$gateway->expects($this->once())
			->method('send');
		$this->random->expects($this->once())
			->method('generate')
			->willReturn('963852');
		$state = State::verifying($user, $gatewayName, $identifier, '963852');
		$this->stateStorage->expects($this->once())
			->method('persist')
			->with($this->equalTo($state))
			->willReturnArgument(0);

		$actualState = $this->setupService->startSetup($user, $gatewayName, $identifier);

		$this->assertEquals($state, $actualState);
	}

	public function testFinishSetupNoVerificationNumberSet() {
		$user = $this->createMock(IUser::class);
		$state = State::disabled($user, 'sms');
		$this->stateStorage->expects($this->once())
			->method('get')
			->willReturn($state);
		$this->stateStorage->expects($this->never())
			->method('persist');
		$this->registry->expects($this->never())
			->method('enableProviderFor');
		$this->expectException(Exception::class);

		$this->setupService->finishSetup($user, 'telegram', '123456');
	}

	public function testFinishSetupWithWrongVerificationNumber() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$state = State::verifying($user, 'telegram', '0123456789', '654321');
		$this->stateStorage->expects($this->once())
			->method('get')
			->willReturn($state);
		$this->stateStorage->expects($this->never())
			->method('persist');
		$this->registry->expects($this->never())
			->method('enableProviderFor');
		$this->expectException(VerificationException::class);

		$this->setupService->finishSetup($user, 'telegram', '123456');
	}

	public function testFinishSetup() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$state = State::verifying($user, 'signal', '0123456789', '123456');
		$this->stateStorage->expects($this->once())
			->method('get')
			->willReturn($state);
		$provider = $this->createMock(AProvider::class);
		$this->providerFactory->expects($this->once())
			->method('get')
			->with('signal')
			->willReturn($provider);
		$this->registry->expects($this->once())
			->method('enableProviderFor')
			->with(
				$provider,
				$user
			);
		$verfied = $state->verify();
		$this->stateStorage->expects($this->once())
			->method('persist')
			->with($this->equalTo($verfied))
			->willReturnArgument(0);

		$actualState = $this->setupService->finishSetup($user, 'signal', '123456');

		$this->assertEquals($verfied, $actualState);
	}
}
