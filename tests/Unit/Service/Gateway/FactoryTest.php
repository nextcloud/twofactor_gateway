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

namespace OCA\TwoFactorGateway\Tests\Unit\Service\Gateway;

use OCA\TwoFactorGateway\Service\Gateway\Factory;
use OCA\TwoFactorGateway\Service\Gateway\Signal\Gateway as SignalGateway;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Gateway as SMSGateway;
use OCA\TwoFactorGateway\Service\Gateway\Telegram\Gateway as TelegramGateway;
use PHPUnit\Framework\TestCase;

class FactoryTest extends TestCase {

	/** @var SignalGateway */
	private $signalGateway;

	/** @var SMSGateway */
	private $smsGateway;

	/** @var TelegramGateway */
	private $telegramGateway;

	/** @var Factory */
	private $factory;

	protected function setUp(): void {
		parent::setUp();

		$this->signalGateway = $this->createMock(SignalGateway::class);
		$this->smsGateway = $this->createMock(SMSGateway::class);
		$this->telegramGateway = $this->createMock(TelegramGateway::class);

		$this->factory = new Factory(
			$this->signalGateway,
			$this->smsGateway,
			$this->telegramGateway
		);
	}

	public function testGetSignalGateway() {
		$gateway = $this->factory->getGateway("signal");

		$this->assertSame($this->signalGateway, $gateway);
	}

	public function testGetSMSGateway() {
		$gateway = $this->factory->getGateway("sms");

		$this->assertSame($this->smsGateway, $gateway);
	}

	public function testGetTelegamGateway() {
		$gateway = $this->factory->getGateway("telegram");

		$this->assertSame($this->telegramGateway, $gateway);
	}

	public function testGetInvalidGateway() {
		$this->expectException(\Exception::class);
		$this->factory->getGateway("wrong");
	}
}
