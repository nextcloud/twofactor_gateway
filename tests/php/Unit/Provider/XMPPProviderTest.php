<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Rainer Dohmen <rdohmen@pensionmoselblick.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider;

use OCA\TwoFactorGateway\Provider\XMPPProvider;
use OCA\TwoFactorGateway\Service\Gateway\XMPP\Gateway;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\ISession;
use OCP\Security\ISecureRandom;
use OCP\Template\ITemplateManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class XMPPProviderTest extends TestCase {
	private Gateway&MockObject $gateway;
	private StateStorage&MockObject $stateStorage;
	private ISession&MockObject $session;
	private ISecureRandom&MockObject $random;
	private IL10N&MockObject $l10n;
	private IInitialState&MockObject $initialState;
	private ITemplateManager&MockObject $templateManager;
	private XMPPProvider $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->gateway = $this->createMock(Gateway::class);
		$this->stateStorage = $this->createMock(StateStorage::class);
		$this->session = $this->createMock(ISession::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->templateManager = $this->createMock(ITemplateManager::class);
		$this->initialState = $this->createMock(IInitialState::class);

		$this->provider = new XMPPProvider(
			$this->gateway,
			$this->stateStorage,
			$this->session,
			$this->random,
			$this->l10n,
			$this->templateManager,
			$this->initialState,
		);
	}


	public function testGetDescription() {
		$translated = 'trans';
		$this->l10n->expects($this->once())
			->method('t')
			->with('Authenticate via XMPP')
			->willReturn($translated);

		$actual = $this->provider->getDescription();

		$this->assertSame($translated, $actual);
	}

	public function testGetId() {
		$expected = 'gateway_xmpp';

		$actual = $this->provider->getId();

		$this->assertSame($expected, $actual);
	}

	public function testGetDisplayName() {
		$translated = 'trans';
		$this->l10n->expects($this->once())
			->method('t')
			->with('XMPP verification')
			->willReturn($translated);

		$actual = $this->provider->getDisplayName();

		$this->assertSame($translated, $actual);
	}
}
