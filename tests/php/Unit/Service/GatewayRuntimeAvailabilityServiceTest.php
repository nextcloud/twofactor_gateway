<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\FieldExposure;
use OCA\TwoFactorGateway\Provider\FieldType;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Service\GatewayFieldSanitizer;
use OCA\TwoFactorGateway\Service\GatewayInstanceViewFactory;
use OCA\TwoFactorGateway\Service\GatewayRouteCandidate;
use OCA\TwoFactorGateway\Service\GatewayRoutingService;
use OCA\TwoFactorGateway\Service\GatewayRuntimeAvailabilityService;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GatewayRuntimeAvailabilityServiceTest extends TestCase {
	private GatewayFactory&MockObject $gatewayFactory;
	private GatewayRoutingService&MockObject $gatewayRoutingService;
	private GatewayRuntimeAvailabilityService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->gatewayFactory = $this->createMock(GatewayFactory::class);
		$this->gatewayRoutingService = $this->createMock(GatewayRoutingService::class);
		$this->service = new GatewayRuntimeAvailabilityService(
			$this->gatewayFactory,
			$this->gatewayRoutingService,
			new GatewayInstanceViewFactory(new GatewayFieldSanitizer()),
		);
	}

	public function testListGatewaysForUserReturnsRuntimeSafeCatalogWithPrefixedInstanceReferences(): void {
		$user = $this->makeUser('alice');

		/** @var IGateway&IProviderCatalogGateway&MockObject $gateway */
		$gateway = $this->createMockForIntersectionOfInterfaces([IGateway::class, IProviderCatalogGateway::class]);
		$gateway->method('getProviderId')->willReturn('whatsapp');
		$gateway->method('getSettings')->willReturn(new Settings(
			name: 'WhatsApp',
			id: 'whatsapp',
			fields: [new FieldDefinition(field: 'admin_secret', prompt: 'Admin secret', type: FieldType::SECRET)],
		));
		$gateway->method('isComplete')->willReturn(false);
		$gateway->method('getProviderSelectorField')->willReturn(new FieldDefinition(
			field: 'provider',
			prompt: 'Provider',
			exposure: FieldExposure::RUNTIME,
		));
		$gateway->method('getProviderCatalog')->willReturn([
			[
				'id' => 'gowhatsapp',
				'name' => 'GoWhatsApp',
				'fields' => [
					new FieldDefinition(field: 'display_name', prompt: 'Display name', exposure: FieldExposure::RUNTIME),
					new FieldDefinition(field: 'token', prompt: 'Token', type: FieldType::SECRET),
				],
			],
		]);

		$childGateway = $this->makeGatewayMock('gowhatsapp', [
			new FieldDefinition(field: 'display_name', prompt: 'Display name', exposure: FieldExposure::RUNTIME),
			new FieldDefinition(field: 'token', prompt: 'Token', type: FieldType::SECRET),
		]);

		$this->gatewayFactory->method('getFqcnList')->willReturn(['whatsapp']);
		$this->gatewayFactory->method('get')->with('whatsapp')->willReturn($gateway);
		$this->gatewayRoutingService->method('resolveCandidatesForUser')->with($user, 'whatsapp')->willReturn([
			$this->makeCandidate($childGateway, 'gowhatsapp', 'gowhatsapp:prod', [
				'id' => 'prod',
				'label' => 'Prod',
				'default' => true,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['display_name' => 'Prod', 'token' => 'secret'],
				'isComplete' => true,
				'groupIds' => ['client-a'],
				'priority' => 10,
			]),
		]);

		$list = $this->service->listGatewaysForUser($user);

		$this->assertCount(1, $list);
		$this->assertSame([], $list[0]['fields']);
		$this->assertSame('provider', $list[0]['providerSelector']['field']);
		$this->assertSame(['display_name'], array_map(static fn (array $field): string => $field['field'], $list[0]['providerCatalog'][0]['fields']));
		$this->assertFalse($list[0]['hasDirectGatewayFallback']);
		$this->assertSame('gowhatsapp', $list[0]['instances'][0]['providerId']);
		$this->assertSame('gowhatsapp:prod', $list[0]['instances'][0]['publicInstanceId']);
		$this->assertSame(['display_name' => 'Prod'], $list[0]['instances'][0]['config']);
	}

	public function testListGatewaysForUserIncludesDirectFallbackWithoutInstanceCandidates(): void {
		$user = $this->makeUser('bob');
		$gateway = $this->makeGatewayMock('sms', [
			new FieldDefinition(field: 'display_name', prompt: 'Display name', exposure: FieldExposure::RUNTIME),
		], true);

		$this->gatewayFactory->method('getFqcnList')->willReturn(['sms']);
		$this->gatewayFactory->method('get')->with('sms')->willReturn($gateway);
		$this->gatewayRoutingService->method('resolveCandidatesForUser')->with($user, 'sms')->willReturn([]);

		$list = $this->service->listGatewaysForUser($user);

		$this->assertCount(1, $list);
		$this->assertSame('sms', $list[0]['id']);
		$this->assertSame([], $list[0]['instances']);
		$this->assertTrue($list[0]['hasDirectGatewayFallback']);
	}

	public function testListGatewaysForUserFiltersOutUnavailableGatewayWithoutFallback(): void {
		$user = $this->makeUser('carol');
		$gateway = $this->makeGatewayMock('xmpp', [
			new FieldDefinition(field: 'username', prompt: 'Username', exposure: FieldExposure::RUNTIME),
		]);

		$this->gatewayFactory->method('getFqcnList')->willReturn(['xmpp']);
		$this->gatewayFactory->method('get')->with('xmpp')->willReturn($gateway);
		$this->gatewayRoutingService->method('resolveCandidatesForUser')
			->with($user, 'xmpp')
			->willThrowException(new MessageTransmissionException('No gateway instance is accessible for this user.'));

		$this->assertSame([], $this->service->listGatewaysForUser($user));
	}

	public function testListAvailableInstancesForUserReturnsEmptyWhenRoutingHasNoAccessibleCandidate(): void {
		$user = $this->makeUser('dave');
		$this->gatewayRoutingService->method('resolveCandidatesForUser')
			->with($user, 'signal')
			->willThrowException(new MessageTransmissionException('No gateway instance is accessible for this user.'));

		$this->assertSame([], $this->service->listAvailableInstancesForUser($user, 'signal'));
	}

	/**
	 * @param list<FieldDefinition> $fields
	 * @return IGateway&MockObject
	 */
	private function makeGatewayMock(string $id, array $fields, bool $isComplete = false): IGateway&MockObject {
		$settings = new Settings(name: ucfirst($id), id: $id, fields: $fields);
		$gateway = $this->createMock(IGateway::class);
		$gateway->method('getProviderId')->willReturn($id);
		$gateway->method('getSettings')->willReturn($settings);
		$gateway->method('isComplete')->willReturn($isComplete);
		return $gateway;
	}

	private function makeUser(string $uid): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}

	/**
	 * @param array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int} $instance
	 */
	private function makeCandidate(IGateway $gateway, string $providerId, string $publicInstanceId, array $instance): GatewayRouteCandidate {
		return GatewayRouteCandidate::fromArray([
			'gateway' => $gateway,
			'providerId' => $providerId,
			'publicInstanceId' => $publicInstanceId,
			'instance' => $instance,
		]);
	}
}
