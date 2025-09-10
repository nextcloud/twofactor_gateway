<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service\Gateway;

use OCA\TwoFactorGateway\Service\Gateway\Factory;
use OCA\TwoFactorGateway\Service\Gateway\Signal\Gateway as SignalGateway;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Gateway as SMSGateway;
use OCA\TwoFactorGateway\Service\Gateway\Telegram\Gateway as TelegramGateway;
use OCA\TwoFactorGateway\Service\Gateway\XMPP\Gateway as XMPPGateway;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FactoryTest extends TestCase {
	private SignalGateway&MockObject $signalGateway;
	private SMSGateway&MockObject $smsGateway;
	private TelegramGateway&MockObject $telegramGateway;
	private XMPPGateway&MockObject $xmppGateway;
	private Factory $factory;

	protected function setUp(): void {
		parent::setUp();

		$this->signalGateway = $this->createMock(SignalGateway::class);
		$this->smsGateway = $this->createMock(SMSGateway::class);
		$this->telegramGateway = $this->createMock(TelegramGateway::class);
		$this->xmppGateway = $this->createMock(XMPPGateway::class);

		$this->factory = new Factory(
			$this->signalGateway,
			$this->smsGateway,
			$this->telegramGateway,
			$this->xmppGateway
		);
	}

	public function testGetSignalGateway() {
		$gateway = $this->factory->getGateway('signal');

		$this->assertSame($this->signalGateway, $gateway);
	}

	public function testGetSMSGateway() {
		$gateway = $this->factory->getGateway('sms');

		$this->assertSame($this->smsGateway, $gateway);
	}

	public function testGetTelegamGateway() {
		$gateway = $this->factory->getGateway('telegram');

		$this->assertSame($this->telegramGateway, $gateway);
	}

	public function testGetXMPPGateway() {
		$gateway = $this->factory->getGateway('xmpp');

		$this->assertSame($this->xmppGateway, $gateway);
	}

	public function testGetInvalidGateway() {
		$this->expectException(\Exception::class);
		$this->factory->getGateway('wrong');
	}
}
