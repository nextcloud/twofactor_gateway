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
use OCA\TwoFactorGateway\Service\Gateway\SMS\Gateway;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\IL10N;
use OCP\ISession;
use OCP\IUser;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SmsProviderTest extends TestCase {

	/** @var IGateway|MockObject */
	private $smsGateway;

	/** @var StateStorage|MockObject */
	private $stateStorage;

	/** @var ISession|MockObject */
	private $session;

	/** @var ISecureRandom|MockObject */
	private $random;

	/** @var IL10n|MockObject */
	private $l10n;

	/** @var SmsProvider */
	private $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->smsGateway = $this->createMock(Gateway::class);
		$this->stateStorage = $this->createMock(StateStorage::class);
		$this->session = $this->createMock(ISession::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->l10n = $this->createMock(IL10N::class);

		$this->provider = new SmsProvider(
			$this->smsGateway,
			$this->stateStorage,
			$this->session,
			$this->random,
			$this->l10n
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
