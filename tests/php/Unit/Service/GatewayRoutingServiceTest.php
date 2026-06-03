<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Service\GatewayConfigService;
use OCA\TwoFactorGateway\Service\GatewayRoutingService;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCA\TwoFactorGateway\Tests\Unit\Service\Support\RuntimeAwareGatewayDouble;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;

class GatewayRoutingServiceTest extends AppTestCase {
	private GatewayFactory&MockObject $gatewayFactory;
	private GatewayConfigService&MockObject $gatewayConfigService;
	private IGroupManager&MockObject $groupManager;
	private GatewayRoutingService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->gatewayFactory = $this->createMock(GatewayFactory::class);
		$this->gatewayConfigService = $this->createMock(GatewayConfigService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->service = new GatewayRoutingService(
			$this->gatewayFactory,
			$this->gatewayConfigService,
			$this->groupManager,
		);
	}

	public function testResolveCandidatesForUserOrdersGroupCandidatesByPriorityDefaultCreatedAtAndPublicId(): void {
		$appConfig = $this->makeInMemoryAppConfig();
		$gateway = new RuntimeAwareGatewayDouble($appConfig);
		$user = $this->createMock(IUser::class);

		$this->gatewayFactory->method('get')->with('runtimeaware')->willReturn($gateway);
		$this->gatewayConfigService->method('listInstances')->with($gateway)->willReturn([
			$this->makeInstance('inst-default', 'Default', true, '2026-01-02T00:00:00+00:00', ['group-a'], 10),
			$this->makeInstance('inst-priority', 'Priority', false, '2026-01-03T00:00:00+00:00', ['group-a'], 20),
			$this->makeInstance('inst-created-first', 'Created first', false, '2026-01-01T00:00:00+00:00', ['group-a'], 10),
			$this->makeInstance('inst-created-second', 'Created second', false, '2026-01-04T00:00:00+00:00', ['group-a'], 10),
		]);
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn(['group-a']);

		$candidates = $this->service->resolveCandidatesForUser($user, 'runtimeaware');

		$this->assertSame(
			['inst-priority', 'inst-default', 'inst-created-first', 'inst-created-second'],
			array_map(static fn ($candidate): string => $candidate->instance->id, $candidates),
		);
	}

	public function testResolveCandidatesForUserUsesOpenInstancesAsFallbackAndKeepsPriorityAheadOfDefault(): void {
		$appConfig = $this->makeInMemoryAppConfig();
		$gateway = new RuntimeAwareGatewayDouble($appConfig);
		$user = $this->createMock(IUser::class);

		$this->gatewayFactory->method('get')->with('runtimeaware')->willReturn($gateway);
		$this->gatewayConfigService->method('listInstances')->with($gateway)->willReturn([
			$this->makeInstance('restricted', 'Restricted', false, '2026-01-01T00:00:00+00:00', ['group-a'], 99),
			$this->makeInstance('open-default', 'Open default', true, '2026-01-02T00:00:00+00:00', [], 0),
			$this->makeInstance('open-priority', 'Open priority', false, '2026-01-03T00:00:00+00:00', [], 10),
		]);
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn([]);

		$candidates = $this->service->resolveCandidatesForUser($user, 'runtimeaware');

		$this->assertSame(['open-priority', 'open-default'], array_map(static fn ($candidate): string => $candidate->instance->id, $candidates));
	}

	public function testResolveCandidatesForUserIgnoresIncompleteInstances(): void {
		$appConfig = $this->makeInMemoryAppConfig();
		$gateway = new RuntimeAwareGatewayDouble($appConfig);
		$user = $this->createMock(IUser::class);

		$this->gatewayFactory->method('get')->with('runtimeaware')->willReturn($gateway);
		$this->gatewayConfigService->method('listInstances')->with($gateway)->willReturn([
			array_merge(
				$this->makeInstance('incomplete', 'Incomplete', false, '2026-01-01T00:00:00+00:00', [], 50),
				['isComplete' => false],
			),
			$this->makeInstance('complete', 'Complete', false, '2026-01-02T00:00:00+00:00', [], 1),
		]);
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn([]);

		$candidates = $this->service->resolveCandidatesForUser($user, 'runtimeaware');

		$this->assertCount(1, $candidates);
		$this->assertSame('complete', $candidates[0]->instance->id);
	}

	public function testResolveCandidatesForUserThrowsWhenNoAccessibleInstanceExists(): void {
		$appConfig = $this->makeInMemoryAppConfig();
		$gateway = new RuntimeAwareGatewayDouble($appConfig);
		$user = $this->createMock(IUser::class);

		$this->gatewayFactory->method('get')->with('runtimeaware')->willReturn($gateway);
		$this->gatewayConfigService->method('listInstances')->with($gateway)->willReturn([
			$this->makeInstance('group-a-only', 'Group A', false, '2026-01-01T00:00:00+00:00', ['group-a'], 10),
		]);
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn(['group-b']);

		$this->expectException(MessageTransmissionException::class);

		$this->service->resolveCandidatesForUser($user, 'runtimeaware');
	}

	public function testListResolvedInstancesIncludesCatalogChildProvidersWithPrefixedPublicIds(): void {
		$appConfig = $this->makeInMemoryAppConfig();
		$catalogGateway = $this->createMockForIntersectionOfInterfaces([\OCA\TwoFactorGateway\Provider\Gateway\IGateway::class, IProviderCatalogGateway::class]);
		$catalogGateway->method('getProviderId')->willReturn('whatsapp');
		$catalogGateway->method('getSettings')->willReturn(new Settings(name: 'WhatsApp', id: 'whatsapp', fields: [new FieldDefinition('base_url', 'Base URL')]));
		$catalogGateway->method('getProviderCatalog')->willReturn([
			['id' => 'gowhatsapp', 'name' => 'GoWhatsApp', 'fields' => []],
		]);
		$childGateway = new RuntimeAwareGatewayDouble($appConfig, 'gowhatsapp');

		$this->gatewayFactory->method('get')->willReturnMap([
			['whatsapp', $catalogGateway],
			['gowhatsapp', $childGateway],
		]);
		$this->gatewayConfigService->method('listInstances')->willReturnCallback(static function (object $gateway): array {
			if ($gateway instanceof RuntimeAwareGatewayDouble) {
				return [[
					'id' => 'inst-child',
					'label' => 'Child',
					'default' => true,
					'createdAt' => '2026-01-01T00:00:00+00:00',
					'config' => ['base_url' => 'https://wa.example.com'],
					'isComplete' => true,
					'groupIds' => [],
					'priority' => 0,
				]];
			}

			return [[
				'id' => 'inst-root',
				'label' => 'Root',
				'default' => true,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['base_url' => 'https://root.example.com'],
				'isComplete' => true,
				'groupIds' => [],
				'priority' => 0,
			]];
		});

		$candidates = $this->service->listResolvedInstances('whatsapp');

		$this->assertSame(['inst-root', 'gowhatsapp:inst-child'], array_map(static fn ($candidate): string => $candidate->publicInstanceId, $candidates));
	}

	/**
	 * @param list<string> $groupIds
	 * @return array{id: string, label: string, default: bool, createdAt: string, config: array{base_url: string}, isComplete: bool, groupIds: list<string>, priority: int}
	 */
	private function makeInstance(string $id, string $label, bool $default, string $createdAt, array $groupIds, int $priority): array {
		return [
			'id' => $id,
			'label' => $label,
			'default' => $default,
			'createdAt' => $createdAt,
			'config' => ['base_url' => 'https://' . $id . '.example.com'],
			'isComplete' => true,
			'groupIds' => $groupIds,
			'priority' => $priority,
		];
	}
}
