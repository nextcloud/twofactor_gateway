<?php

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\TwoFactorGateway\Exception\IdentifierMissingException;
use OCA\TwoFactorGateway\Exception\VerificationException;
use OCA\TwoFactorGateway\Exception\VerificationTransmissionException;
use OCA\TwoFactorGateway\Provider\SmsProvider;
use OCA\TwoFactorGateway\Provider\State;
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\SetupService;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\IUser;
use OCP\Security\ISecureRandom;
use PHPUnit_Framework_MockObject_MockObject;

class SetupServiceTest extends TestCase {

	/** @var StateStorage|PHPUnit_Framework_MockObject_MockObject */
	private $stateStorage;

	/** @var IGateway|PHPUnit_Framework_MockObject_MockObject */
	private $gateway;

	/** @var ISecureRandom|PHPUnit_Framework_MockObject_MockObject */
	private $random;

	/** @var SmsProvider|PHPUnit_Framework_MockObject_MockObject */
	private $provider;

	/** @var IRegistry|PHPUnit_Framework_MockObject_MockObject */
	private $registry;

	/** @var SetupService */
	private $setupService;

	protected function setUp() {
		parent::setUp();

		$this->stateStorage = $this->createMock(StateStorage::class);
		$this->gateway = $this->createMock(IGateway::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->provider = $this->createMock(SmsProvider::class);
		$this->registry = $this->createMock(IRegistry::class);

		$this->setupService = new SetupService(
			$this->stateStorage,
			$this->gateway,
			$this->random,
			$this->provider,
			$this->registry
		);
	}

	public function testStartSetupTransmissionError() {
		$identifier = '1234';
		$user = $this->createMock(IUser::class);
		$this->gateway->expects($this->once())
			->method('send')
			->willThrowException(new VerificationTransmissionException());
		$this->expectException(VerificationTransmissionException::class);

		$this->setupService->startSetup($user, $identifier);
	}

	public function testStartSetup() {
		$identifier = '0123456789';
		$gatewayName = 'websms';
		$this->gateway->method('getShortName')->willReturn($gatewayName);
		$user = $this->createMock(IUser::class);
		$this->gateway->expects($this->once())
			->method('send');
		$this->random->expects($this->once())
			->method('generate')
			->willReturn('963852');
		$state = State::verifying($user, $gatewayName, $identifier, '963852');
		$this->stateStorage->expects($this->once())
			->method('persist')
			->with($this->equalTo($state))
			->willReturnArgument(0);

		$actualState = $this->setupService->startSetup($user, $identifier);

		$this->assertEquals($state, $actualState);
	}

	public function testFinishSetupNoVerificationNumberSet() {
		$user = $this->createMock(IUser::class);
		$state = State::disabled($user);
		$this->stateStorage->expects($this->once())
			->method('get')
			->willReturn($state);
		$this->stateStorage->expects($this->never())
			->method('persist');
		$this->registry->expects($this->never())
			->method('enableProviderFor');
		$this->expectException(\Exception::class);

		$this->setupService->finishSetup($user, '123456');
	}

	public function testFinishSetupWithWrongVerificationNumber() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$state = State::verifying($user, 'websms', '0123456789', '654321');
		$this->stateStorage->expects($this->once())
			->method('get')
			->willReturn($state);
		$this->stateStorage->expects($this->never())
			->method('persist');
		$this->registry->expects($this->never())
			->method('enableProviderFor');
		$this->expectException(VerificationException::class);

		$this->setupService->finishSetup($user, '123456');
	}

	public function testFinishSetup() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$state = State::verifying($user, 'websms', '0123456789', '123456');
		$this->stateStorage->expects($this->once())
			->method('get')
			->willReturn($state);
		$this->registry->expects($this->once())
			->method('enableProviderFor')
			->with(
				$this->provider,
				$user
			);
		$verfied = $state->verify();
		$this->stateStorage->expects($this->once())
			->method('persist')
			->with($this->equalTo($verfied))
			->willReturnArgument(0);

		$actualState = $this->setupService->finishSetup($user, '123456');

		$this->assertEquals($verfied, $actualState);
	}

}
