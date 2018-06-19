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

namespace OCA\TwoFactorGateawy\Tests\Unit\Service;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OC\Accounts\AccountManager;
use OCA\TwoFactorGateawy\Exception\PhoneNumberMissingException;
use OCA\TwoFactorGateawy\Exception\VerificationException;
use OCA\TwoFactorGateawy\Exception\VerificationTransmissionException;
use OCA\TwoFactorGateawy\Service\ISmsService;
use OCA\TwoFactorGateawy\Service\SetupService;
use OCP\IConfig;
use OCP\IUser;
use OCP\Security\ISecureRandom;
use PHPUnit_Framework_MockObject_MockObject;

class SetupServiceTest extends TestCase {

	/** @var IConfig|PHPUnit_Framework_MockObject_MockObject */
	private $config;

	/** @var AccountManager|PHPUnit_Framework_MockObject_MockObject */
	private $accountManager;

	/** @var ISmsService|PHPUnit_Framework_MockObject_MockObject */
	private $smsService;

	/** @var ISecureRandom|PHPUnit_Framework_MockObject_MockObject */
	private $random;

	/** @var SetupService */
	private $setupService;

	protected function setUp() {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->accountManager = $this->createMock(AccountManager::class);
		$this->smsService = $this->createMock(ISmsService::class);
		$this->random = $this->createMock(ISecureRandom::class);

		$this->setupService = new SetupService($this->config, $this->accountManager, $this->smsService, $this->random);
	}

	public function testStartSetupNoPhoneNumberSet() {
		$user = $this->createMock(IUser::class);
		$this->accountManager->expects($this->once())
			->method('getUser')
			->with($user)
			->willReturn([]);
		$this->expectException(PhoneNumberMissingException::class);

		$this->setupService->startSetup($user);
	}

	public function testStartSetupEmptyPhoneNumberSet() {
		$user = $this->createMock(IUser::class);
		$this->accountManager->expects($this->once())
			->method('getUser')
			->with($user)
			->willReturn([
				AccountManager::PROPERTY_PHONE => '',
		]);
		$this->expectException(PhoneNumberMissingException::class);

		$this->setupService->startSetup($user);
	}

	public function testStartSetupTransmissionError() {
		$user = $this->createMock(IUser::class);
		$this->accountManager->expects($this->once())
			->method('getUser')
			->with($user)
			->willReturn([
				AccountManager::PROPERTY_PHONE => [
					'value' => '0123456789',
				],
		]);
		$this->smsService->expects($this->once())
			->method('send')
			->willThrowException(new VerificationTransmissionException());
		$this->expectException(VerificationTransmissionException::class);

		$this->setupService->startSetup($user);
	}

	public function testStartSetup() {
		$user = $this->createMock(IUser::class);
		$this->accountManager->expects($this->once())
			->method('getUser')
			->with($user)
			->willReturn([
				AccountManager::PROPERTY_PHONE => [
					'value' => '0123456789',
				],
		]);
		$this->smsService->expects($this->once())
			->method('send');
		$this->random->expects($this->once())
			->method('generate')
			->willReturn('963852');
		$user->method('getUID')->willReturn('user123');
		$this->config->expects($this->at(0))
			->method('setUserValue')
			->with('user123', 'twofactor_gateway', 'phone', '0123456789');
		$this->config->expects($this->at(1))
			->method('setUserValue')
			->with('user123', 'twofactor_gateway', 'verification_code', '963852');
		$this->config->expects($this->at(2))
			->method('setUserValue')
			->with('user123', 'twofactor_gateway', 'verified', 'false');

		$this->setupService->startSetup($user);
	}

	public function testFinishSetupNoVerificationNumberSet() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$this->config->expects($this->once())
			->method('getUserValue')
			->with('user123', 'twofactor_gateway', 'verification_code', null)
			->willReturn(null);
		$this->expectException(\Exception::class);

		$this->setupService->finishSetup($user, '123456');
	}

	public function testFinishSetupWithWrongVerificationNumber() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$this->config->expects($this->once())
			->method('getUserValue')
			->with('user123', 'twofactor_gateway', 'verification_code', null)
			->willReturn('111111');
		$this->expectException(VerificationException::class);

		$this->setupService->finishSetup($user, '123456');
	}

	public function testFinishSetup() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$this->config->expects($this->once())
			->method('getUserValue')
			->with('user123', 'twofactor_gateway', 'verification_code', null)
			->willReturn('123456');
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('user123', 'twofactor_gateway', 'verified', 'true');

		$this->setupService->finishSetup($user, '123456');
	}

}
