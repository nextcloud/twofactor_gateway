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

use OCA\TwoFactorGateway\Provider\TelegramProvider;
use OCA\TwoFactorGateway\Service\Gateway\Telegram\Gateway;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\IL10N;
use OCP\ISession;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TelegramProviderTest extends TestCase {

	/** @var Gateway|MockObject */
	private $gateway;

	/** @var StateStorage|MockObject */
	private $stateStorage;

	/** @var ISession|MockObject */
	private $session;

	/** @var ISecureRandom|MockObject */
	private $random;

	/** @var IL10N|MockObject */
	private $l10n;

	/** @var TelegramProvider */
	private $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->gateway = $this->createMock(Gateway::class);
		$this->stateStorage = $this->createMock(StateStorage::class);
		$this->session = $this->createMock(ISession::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->l10n = $this->createMock(IL10N::class);

		$this->provider = new TelegramProvider(
			$this->gateway,
			$this->stateStorage,
			$this->session,
			$this->random,
			$this->l10n
		);
	}


	public function testGetDescription() {
		$translated = 'trans';
		$this->l10n->expects($this->once())
			->method('t')
			->with('Authenticate via Telegram')
			->willReturn($translated);

		$actual = $this->provider->getDescription();

		$this->assertSame($translated, $actual);
	}

	public function testGetId() {
		$expected = 'gateway_telegram';

		$actual = $this->provider->getId();

		$this->assertSame($expected, $actual);
	}

	public function testGetDisplayName() {
		$translated = 'trans';
		$this->l10n->expects($this->once())
			->method('t')
			->with('Telegram verification')
			->willReturn($translated);

		$actual = $this->provider->getDisplayName();

		$this->assertSame($translated, $actual);
	}
}
