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

namespace OCA\TwoFactorGateway\Tests\Unit\Provider;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\TwoFactorGateway\Provider\SmsProvider;
use OCA\TwoFactorGateway\Provider\State;
use OCA\TwoFactorGateway\Service\IGateway;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\IConfig;
use OCP\IUser;
use PHPUnit_Framework_MockObject_MockObject;

class StateStorageTest extends TestCase {

	/** @var IConfig|PHPUnit_Framework_MockObject_MockObject */
	private $config;

	/** @var IGateway|PHPUnit_Framework_MockObject_MockObject */
	private $gateway;

	/** @var StateStorage */
	private $storage;

	protected function setUp() {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->gateway = $this->createMock(IGateway::class);

		$this->storage = new StateStorage($this->config, $this->gateway);
	}

	public function testGetNoDataYet() {
		$uid = 'user123';
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->config->method('getUserValue')
			->willReturnMap([
				[$uid, 'twofactor_gateway', 'verified', 'false', 'false'],
			]);

		$state = $this->storage->get($user);

		$this->assertSame(SmsProvider::STATE_DISABLED, $state->getState());
	}

	public function testGetVerifyingState() {
		$uid = 'user123';
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->config->method('getUserValue')
			->willReturnMap([
				[$uid, 'twofactor_gateway', 'verified', 'false', 'false'],
				[$uid, 'twofactor_gateway', 'identifier', null, '0123456789'],
				[$uid, 'twofactor_gateway', 'verification_code', null, '123456'],
			]);

		$state = $this->storage->get($user);

		$this->assertSame(SmsProvider::STATE_VERIFYING, $state->getState());
		$this->assertSame('0123456789', $state->getIdentifier());
		$this->assertSame('123456', $state->getVerificationCode());
	}

	public function testGetEnabledState() {
		$uid = 'user123';
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->config->method('getUserValue')
			->willReturnMap([
				[$uid, 'twofactor_gateway', 'verified', 'false', 'true'],
				[$uid, 'twofactor_gateway', 'identifier', null, '0123456789'],
			]);

		$state = $this->storage->get($user);

		$this->assertSame(SmsProvider::STATE_ENABLED, $state->getState());
		$this->assertSame('0123456789', $state->getIdentifier());
	}

	public function testDisabledState() {
		$uid = 'user123';
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->config->method('getUserValue')
			->willReturnMap([
				[$uid, 'twofactor_gateway', 'verified', 'false', 'false'],
				[$uid, 'twofactor_gateway', 'identifier', null, '0123456789'],
			]);

		$state = $this->storage->get($user);

		$this->assertSame(SmsProvider::STATE_DISABLED, $state->getState());
		$this->assertSame('0123456789', $state->getIdentifier());
	}

	public function testPersistVerifyingState() {
		$user = $this->createMock(IUser::class);
		$uid = 'user321';
		$user->method('getUID')->willReturn($uid);
		$state = State::verifying(
			$user,
			'telegram',
			'0123456789',
			'1234'
		);
		$this->config
			->expects($this->exactly(3))
			->method('setUserValue')
			->withConsecutive(
				[$uid, 'twofactor_gateway', 'identifier', '0123456789'],
				[$uid, 'twofactor_gateway', 'verification_code', '1234'],
				[$uid, 'twofactor_gateway', 'verified', 'false']
			);

		$persisted = $this->storage->persist($state);

		$this->assertSame($persisted, $state);
	}

	public function testVerifiedState() {
		$user = $this->createMock(IUser::class);
		$uid = 'user321';
		$user->method('getUID')->willReturn($uid);
		$state = new State(
			$user,
			SmsProvider::STATE_ENABLED,
			'telegram',
			'0123456789',
			'1234'
		);
		$this->config
			->expects($this->exactly(3))
			->method('setUserValue')
			->withConsecutive(
				[$uid, 'twofactor_gateway', 'identifier', '0123456789'],
				[$uid, 'twofactor_gateway', 'verification_code', '1234'],
				[$uid, 'twofactor_gateway', 'verified', 'true']
			);

		$persisted = $this->storage->persist($state);

		$this->assertSame($persisted, $state);
	}


}