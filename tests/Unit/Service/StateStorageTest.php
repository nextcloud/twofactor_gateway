<?php

declare(strict_types=1);

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

use OCA\TwoFactorGateway\Provider\SmsProvider;
use OCA\TwoFactorGateway\Provider\State;
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\IConfig;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StateStorageTest extends TestCase {

	/** @var IConfig|MockObject */
	private $config;

	/** @var IGateway|MockObject */
	private $gateway;

	/** @var StateStorage */
	private $storage;

	protected function setUp(): void {
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
				[$uid, 'twofactor_gateway', 'sms_verified', 'false', 'false'],
				[$uid, 'twofactor_gateway', 'sms_identifier', '', ''],
				[$uid, 'twofactor_gateway', 'sms_verification_code', '', ''],
			]);

		$state = $this->storage->get($user, 'sms');

		$this->assertSame(SmsProvider::STATE_DISABLED, $state->getState());
	}

	public function testGetVerifyingState() {
		$uid = 'user123';
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->config->method('getUserValue')
			->willReturnMap([
				[$uid, 'twofactor_gateway', 'signal_verified', 'false', 'false'],
				[$uid, 'twofactor_gateway', 'signal_identifier', '', '0123456789'],
				[$uid, 'twofactor_gateway', 'signal_verification_code', '', '123456'],
			]);

		$state = $this->storage->get($user, 'signal');

		$this->assertSame(SmsProvider::STATE_VERIFYING, $state->getState());
		$this->assertSame('0123456789', $state->getIdentifier());
		$this->assertSame('123456', $state->getVerificationCode());
		$this->assertSame('signal', $state->getGatewayName());
	}

	public function testGetEnabledState() {
		$uid = 'user123';
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->config->method('getUserValue')
			->willReturnMap([
				[$uid, 'twofactor_gateway', 'telegram_verified', 'false', 'true'],
				[$uid, 'twofactor_gateway', 'telegram_identifier', '', '0123456789'],
			]);

		$state = $this->storage->get($user, 'telegram');

		$this->assertSame(SmsProvider::STATE_ENABLED, $state->getState());
		$this->assertSame('0123456789', $state->getIdentifier());
		$this->assertSame('telegram', $state->getGatewayName());
	}

	public function testDisabledState() {
		$uid = 'user123';
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->config->method('getUserValue')
			->willReturnMap([
				[$uid, 'twofactor_gateway', 'sms_verified', 'false', 'false'],
				[$uid, 'twofactor_gateway', 'sms_identifier', '', '0123456789'],
				[$uid, 'twofactor_gateway', 'sms_verification_code', '', ''],
			]);

		$state = $this->storage->get($user, 'sms');

		$this->assertSame(SmsProvider::STATE_DISABLED, $state->getState());
		$this->assertSame('0123456789', $state->getIdentifier());
		$this->assertSame('sms', $state->getGatewayName());
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
				[$uid, 'twofactor_gateway', 'telegram_identifier', '0123456789'],
				[$uid, 'twofactor_gateway', 'telegram_verification_code', '1234'],
				[$uid, 'twofactor_gateway', 'telegram_verified', 'false']
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
				[$uid, 'twofactor_gateway', 'telegram_identifier', '0123456789'],
				[$uid, 'twofactor_gateway', 'telegram_verification_code', '1234'],
				[$uid, 'twofactor_gateway', 'telegram_verified', 'true']
			);

		$persisted = $this->storage->persist($state);

		$this->assertSame($persisted, $state);
	}
}
