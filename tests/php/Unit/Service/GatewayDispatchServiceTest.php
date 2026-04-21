<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Service\GatewayConfigService;
use OCA\TwoFactorGateway\Service\GatewayDispatchService;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GatewayDispatchServiceTest extends AppTestCase {
	private GatewayFactory&MockObject $gatewayFactory;
	private GatewayConfigService&MockObject $gatewayConfigService;
	private IGroupManager&MockObject $groupManager;
	private LoggerInterface&MockObject $logger;
	private GatewayDispatchService $service;
	private IAppConfig $appConfig;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->makeInMemoryAppConfig();
		$this->gatewayFactory = $this->createMock(GatewayFactory::class);
		$this->gatewayConfigService = $this->createMock(GatewayConfigService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->service = new GatewayDispatchService(
			$this->gatewayFactory,
			$this->gatewayConfigService,
			$this->groupManager,
			$this->logger,
		);
	}

	public function testSendWithInstanceUsesInstanceRuntimeConfiguration(): void {
		RuntimeAwareGatewayDouble::$sentBaseUrls = [];
		$this->appConfig->setValueString('twofactor_gateway', 'runtimeaware_base_url', 'https://global.example.com');
		$gateway = new RuntimeAwareGatewayDouble($this->appConfig);

		$this->gatewayFactory->method('get')->with('runtimeaware')->willReturn($gateway);
		$this->gatewayConfigService->method('getInstance')->with($gateway, 'inst-a')->willReturn([
			'id' => 'inst-a',
			'providerId' => 'runtimeaware',
			'label' => 'Client A',
			'default' => false,
			'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['base_url' => 'https://instance-a.example.com'],
			'isComplete' => true,
			'groupIds' => [],
			'priority' => 0,
		]);

		$this->service->sendWithInstance('runtimeaware', 'inst-a', '+5511999990000', 'Hello');

		$this->assertSame(['https://instance-a.example.com'], RuntimeAwareGatewayDouble::$sentBaseUrls);
	}

	public function testSendWithReferenceResolvesCatalogChildInstance(): void {
		RuntimeAwareGatewayDouble::$sentBaseUrls = [];
		$catalogGateway = $this->createMockForIntersectionOfInterfaces([\OCA\TwoFactorGateway\Provider\Gateway\IGateway::class, IProviderCatalogGateway::class]);
		$catalogGateway->method('getProviderId')->willReturn('whatsapp');
		$catalogGateway->method('getSettings')->willReturn(new Settings(name: 'WhatsApp', id: 'whatsapp', fields: []));
		$catalogGateway->method('getProviderCatalog')->willReturn([
			['id' => 'gowhatsapp', 'name' => 'WhatsApp', 'fields' => []],
		]);
		$childGateway = new RuntimeAwareGatewayDouble($this->appConfig, 'gowhatsapp');

		$this->gatewayFactory->method('get')->willReturnMap([
			['whatsapp', $catalogGateway],
			['gowhatsapp', $childGateway],
		]);
		$this->gatewayConfigService->method('listInstances')->willReturnCallback(static function (object $gateway): array {
			if ($gateway instanceof RuntimeAwareGatewayDouble && $gateway->getProviderId() === 'gowhatsapp') {
				return [[
					'id' => 'inst-b',
					'providerId' => 'gowhatsapp',
					'label' => 'Client B',
					'default' => true,
					'createdAt' => '2026-01-01T00:00:00+00:00',
					'config' => ['base_url' => 'https://catalog-child.example.com'],
					'isComplete' => true,
					'groupIds' => [],
					'priority' => 0,
				]];
			}

			return [];
		});

		$this->service->sendWithReference('whatsapp', 'gowhatsapp:inst-b', '+5511999990000', 'Hello');

		$this->assertSame(['https://catalog-child.example.com'], RuntimeAwareGatewayDouble::$sentBaseUrls);
	}

	/**
	 * @dataProvider priorityOrderingProvider
	 *
	 * @param list<string> $userGroupIds
	 * @param list<array{id: string, providerId: string, label: string, default: bool, createdAt: string, config: array{base_url: string}, isComplete: bool, groupIds: list<string>, priority: int}> $instances
	 * @param list<string> $expectedSentBaseUrls
	 */
	public function testSendForUserOrdersCandidatesByPriority(array $userGroupIds, array $instances, array $expectedSentBaseUrls, string $expectedInstanceId): void {
		RuntimeAwareGatewayDouble::$sentBaseUrls = [];
		$gateway = new RuntimeAwareGatewayDouble($this->appConfig);
		$user = $this->createMock(IUser::class);

		$this->gatewayFactory->method('get')->with('runtimeaware')->willReturn($gateway);
		$this->gatewayConfigService->method('listInstances')->with($gateway)->willReturn($instances);
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn($userGroupIds);

		$result = $this->service->sendForUser($user, 'runtimeaware', '+5511999990000', 'Hello');

		$this->assertSame($expectedSentBaseUrls, RuntimeAwareGatewayDouble::$sentBaseUrls);
		$this->assertSame($expectedInstanceId, $result['instanceId']);
	}

	/**
	 * @return iterable<string, array{0: list<string>, 1: list<array{id: string, providerId: string, label: string, default: bool, createdAt: string, config: array{base_url: string}, isComplete: bool, groupIds: list<string>, priority: int}>, 2: list<string>, 3: string}>
	 */
	public static function priorityOrderingProvider(): iterable {
		yield 'group candidates prefer higher priority first' => [
			['group-a', 'group-b'],
			[
				[
					'id' => 'inst-low',
					'providerId' => 'runtimeaware',
					'label' => 'Low',
					'default' => false,
					'createdAt' => '2026-01-01T00:00:00+00:00',
					'config' => ['base_url' => 'https://ok-low.example.com'],
					'isComplete' => true,
					'groupIds' => ['group-a'],
					'priority' => 10,
				],
				[
					'id' => 'inst-high',
					'providerId' => 'runtimeaware',
					'label' => 'High',
					'default' => false,
					'createdAt' => '2026-01-02T00:00:00+00:00',
					'config' => ['base_url' => 'https://fail-high.example.com'],
					'isComplete' => true,
					'groupIds' => ['group-b'],
					'priority' => 20,
				],
			],
			['https://fail-high.example.com', 'https://ok-low.example.com'],
			'inst-low',
		];

		yield 'fallback candidates prefer higher priority first' => [
			[],
			[
				[
					'id' => 'open-low',
					'providerId' => 'runtimeaware',
					'label' => 'Open Low',
					'default' => false,
					'createdAt' => '2026-01-01T00:00:00+00:00',
					'config' => ['base_url' => 'https://ok-open-low.example.com'],
					'isComplete' => true,
					'groupIds' => [],
					'priority' => 10,
				],
				[
					'id' => 'open-high',
					'providerId' => 'runtimeaware',
					'label' => 'Open High',
					'default' => false,
					'createdAt' => '2026-01-02T00:00:00+00:00',
					'config' => ['base_url' => 'https://fail-open-high.example.com'],
					'isComplete' => true,
					'groupIds' => [],
					'priority' => 20,
				],
			],
			['https://fail-open-high.example.com', 'https://ok-open-low.example.com'],
			'open-low',
		];
	}

	public function testSendForUserSingleInstanceNoGroupAllowsEveryone(): void {
		RuntimeAwareGatewayDouble::$sentBaseUrls = [];
		$gateway = new RuntimeAwareGatewayDouble($this->appConfig);
		$user = $this->createMock(IUser::class);

		$this->gatewayFactory->method('get')->with('runtimeaware')->willReturn($gateway);
		$this->gatewayConfigService->method('listInstances')->with($gateway)->willReturn([
			[
				'id' => 'signal-inst',
				'providerId' => 'runtimeaware',
				'label' => 'Signal',
				'default' => true,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['base_url' => 'https://signal.example.com'],
				'isComplete' => true,
				'groupIds' => [],
				'priority' => 0,
			],
		]);
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn([]);

		$result = $this->service->sendForUser($user, 'runtimeaware', '+5511999990000', 'code');

		$this->assertSame('signal-inst', $result['instanceId']);
	}

	public function testSendForUserSingleInstanceWithGroupAllowsGroupMember(): void {
		RuntimeAwareGatewayDouble::$sentBaseUrls = [];
		$gateway = new RuntimeAwareGatewayDouble($this->appConfig);
		$user = $this->createMock(IUser::class);

		$this->gatewayFactory->method('get')->with('runtimeaware')->willReturn($gateway);
		$this->gatewayConfigService->method('listInstances')->with($gateway)->willReturn([
			[
				'id' => 'signal-inst',
				'providerId' => 'runtimeaware',
				'label' => 'Signal',
				'default' => true,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['base_url' => 'https://signal.example.com'],
				'isComplete' => true,
				'groupIds' => ['signal-users'],
				'priority' => 0,
			],
		]);
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn(['signal-users']);

		$result = $this->service->sendForUser($user, 'runtimeaware', '+5511999990000', 'code');

		$this->assertSame('signal-inst', $result['instanceId']);
	}

	public function testSendForUserSingleInstanceWithGroupBlocksNonMember(): void {
		$gateway = new RuntimeAwareGatewayDouble($this->appConfig);
		$user = $this->createMock(IUser::class);

		$this->gatewayFactory->method('get')->with('runtimeaware')->willReturn($gateway);
		$this->gatewayConfigService->method('listInstances')->with($gateway)->willReturn([
			[
				'id' => 'signal-inst',
				'providerId' => 'runtimeaware',
				'label' => 'Signal',
				'default' => true,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['base_url' => 'https://signal.example.com'],
				'isComplete' => true,
				'groupIds' => ['signal-users'],
				'priority' => 0,
			],
		]);
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn(['other-group']);

		$this->expectException(MessageTransmissionException::class);

		$this->service->sendForUser($user, 'runtimeaware', '+5511999990000', 'code');
	}

	public function testSendForUserUsesOpenInstanceAsFallbackWhenNoGroupMappingMatches(): void {
		RuntimeAwareGatewayDouble::$sentBaseUrls = [];
		$gateway = new RuntimeAwareGatewayDouble($this->appConfig);
		$user = $this->createMock(IUser::class);

		$this->gatewayFactory->method('get')->with('runtimeaware')->willReturn($gateway);
		$this->gatewayConfigService->method('listInstances')->with($gateway)->willReturn([
			[
				'id' => 'group-a-inst',
				'providerId' => 'runtimeaware',
				'label' => 'Group A instance',
				'default' => false,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['base_url' => 'https://group-a.example.com'],
				'isComplete' => true,
				'groupIds' => ['group-a'],
				'priority' => 0,
			],
			[
				'id' => 'open-inst',
				'providerId' => 'runtimeaware',
				'label' => 'Open (no group)',
				'default' => false,
				'createdAt' => '2026-01-02T00:00:00+00:00',
				'config' => ['base_url' => 'https://ok.example.com'],
				'isComplete' => true,
				'groupIds' => [],
				'priority' => 0,
			],
		]);
		$this->groupManager->method('getUserGroupIds')->with($user)->willReturn([]);

		$result = $this->service->sendForUser($user, 'runtimeaware', '+5511999990000', 'Hello');

		$this->assertSame(['https://ok.example.com'], RuntimeAwareGatewayDouble::$sentBaseUrls);
		$this->assertSame('open-inst', $result['instanceId']);
	}
}

class RuntimeAwareGatewayDouble extends AGateway {
	/** @var list<string> */
	public static array $sentBaseUrls = [];

	public function __construct(
		IAppConfig $appConfig,
		private string $gatewayId = 'runtimeaware',
	) {
		parent::__construct($appConfig);
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		$baseUrl = $this->getBaseUrl();
		self::$sentBaseUrls[] = $baseUrl;
		if (str_contains($baseUrl, 'fail')) {
			throw new MessageTransmissionException('simulated failure');
		}
	}

	#[\Override]
	public function createSettings(): Settings {
		return new Settings(
			name: 'RuntimeAware',
			id: $this->gatewayId,
			fields: [new FieldDefinition('base_url', 'Base URL')],
		);
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		return 0;
	}

	#[\Override]
	public function getProviderId(): string {
		return $this->gatewayId;
	}
}
