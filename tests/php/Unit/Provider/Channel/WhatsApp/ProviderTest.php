<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\WhatsApp;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Provider\State;
use OCA\TwoFactorGateway\Service\GatewayDispatchService;
use OCA\TwoFactorGateway\Service\GatewayRuntimeAvailabilityService;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\ISession;
use OCP\IUser;
use OCP\Security\ISecureRandom;
use OCP\Template\ITemplate;
use OCP\Template\ITemplateManager;
use PHPUnit\Framework\TestCase;

class ProviderTest extends TestCase {
	public function testPersonalAndLoginSetupAreAvailableFromUserScopedInstance(): void {
		$gateway = $this->createMock(IGateway::class);
		$gateway->method('getProviderId')->willReturn('whatsapp');
		$gateway->method('getSettings')->willReturn(new Settings(name: 'WhatsApp', id: 'whatsapp', fields: []));
		$gateway->method('isComplete')->willReturn(false);
		$factory = $this->createMock(GatewayFactory::class);
		$factory->method('get')->willReturn($gateway);
		$user = $this->createMock(IUser::class);
		$availability = $this->createMock(GatewayRuntimeAvailabilityService::class);
		$availability->expects($this->exactly(2))->method('isAvailableForUser')
			->with($user, 'whatsapp')->willReturn(true);

		$provider = new Provider(
			$factory,
			$this->createMock(StateStorage::class),
			$this->createMock(ISession::class),
			$this->createMock(ISecureRandom::class),
			$this->createMock(IL10N::class),
			$this->createMock(ITemplateManager::class),
			$this->createMock(IInitialState::class),
			$this->createMock(GatewayDispatchService::class),
			$availability,
		);

		$personal = $provider->getPersonalSettings($user);
		$login = $provider->getLoginSetup($user);
		$this->assertTrue((new \ReflectionProperty($personal, 'isComplete'))->getValue($personal));
		$this->assertTrue((new \ReflectionProperty($login, 'isComplete'))->getValue($login));
	}

	public function testLoginChallengeIsSentUsingUserScopedInstanceAndOtp(): void {
		$user = $this->createMock(IUser::class);
		$gateway = $this->createMock(IGateway::class);
		$gateway->method('getProviderId')->willReturn('whatsapp');
		$gateway->method('getSettings')->willReturn(new Settings(name: 'WhatsApp', id: 'whatsapp', fields: []));
		$factory = $this->createMock(GatewayFactory::class);
		$factory->method('get')->willReturn($gateway);
		$storage = $this->createMock(StateStorage::class);
		$storage->method('get')->with($user, 'whatsapp')
			->willReturn(State::verifying($user, 'whatsapp', '+5511999990000', '000000'));
		$random = $this->createMock(ISecureRandom::class);
		$random->method('generate')->willReturn('654321');
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $message): string => $message);
		$templateManager = $this->createMock(ITemplateManager::class);
		$templateManager->method('getTemplate')->willReturn($this->createMock(ITemplate::class));
		$dispatch = $this->createMock(GatewayDispatchService::class);
		$dispatch->expects($this->once())->method('sendForUser')
			->with($user, 'whatsapp', '+5511999990000', $this->anything(), ['code' => '654321'])
			->willReturn(['providerId' => 'whatsappbusiness', 'instanceId' => 'inst',
				'publicInstanceId' => 'whatsappbusiness:inst', 'label' => 'Production']);

		$provider = new Provider(
			$factory,
			$storage,
			$this->createMock(ISession::class),
			$random,
			$l10n,
			$templateManager,
			$this->createMock(IInitialState::class),
			$dispatch,
			$this->createMock(GatewayRuntimeAvailabilityService::class),
		);

		$this->assertInstanceOf(ITemplate::class, $provider->getTemplate($user));
	}
}
