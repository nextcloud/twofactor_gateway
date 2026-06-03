<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service;

use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Gateway\ITestResultEnricher;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Service\GatewayDispatchService;
use OCA\TwoFactorGateway\Service\GatewayInstanceRecord;
use OCA\TwoFactorGateway\Service\GatewayRouteCandidate;
use OCA\TwoFactorGateway\Service\GatewayRoutingService;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCA\TwoFactorGateway\Tests\Unit\Service\Support\RuntimeAwareGatewayDouble;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class GatewayDispatchServiceTest extends AppTestCase {
	private GatewayRoutingService&MockObject $gatewayRoutingService;
	private LoggerInterface&MockObject $logger;
	private GatewayDispatchService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->gatewayRoutingService = $this->createMock(GatewayRoutingService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->service = new GatewayDispatchService(
			$this->gatewayRoutingService,
			$this->logger,
		);
	}

	public function testSendWithInstanceUsesResolvedInstanceRuntimeConfiguration(): void {
		RuntimeAwareGatewayDouble::$sentBaseUrls = [];
		$appConfig = $this->makeInMemoryAppConfig();
		$appConfig->setValueString('twofactor_gateway', 'runtimeaware_base_url', 'https://global.example.com');
		$gateway = new RuntimeAwareGatewayDouble($appConfig);

		$this->gatewayRoutingService->expects($this->once())
			->method('resolveProviderInstance')
			->with('runtimeaware', 'inst-a')
			->willReturn($this->makeCandidate($gateway, 'runtimeaware', 'inst-a', [
				'id' => 'inst-a',
				'label' => 'Client A',
				'default' => false,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['base_url' => 'https://instance-a.example.com'],
				'isComplete' => true,
				'groupIds' => [],
				'priority' => 0,
			]));

		$this->service->sendWithInstance('runtimeaware', 'inst-a', '+5511999990000', 'Hello');

		$this->assertSame(['https://instance-a.example.com'], RuntimeAwareGatewayDouble::$sentBaseUrls);
	}

	public function testSendWithReferenceUsesResolvedCandidate(): void {
		RuntimeAwareGatewayDouble::$sentBaseUrls = [];
		$appConfig = $this->makeInMemoryAppConfig();
		$gateway = new RuntimeAwareGatewayDouble($appConfig, 'gowhatsapp');

		$this->gatewayRoutingService->expects($this->once())
			->method('resolveGatewayInstanceReference')
			->with('whatsapp', 'gowhatsapp:inst-b')
			->willReturn($this->makeCandidate($gateway, 'gowhatsapp', 'gowhatsapp:inst-b', [
				'id' => 'inst-b',
				'label' => 'Client B',
				'default' => true,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['base_url' => 'https://catalog-child.example.com'],
				'isComplete' => true,
				'groupIds' => [],
				'priority' => 0,
			]));

		$this->service->sendWithReference('whatsapp', 'gowhatsapp:inst-b', '+5511999990000', 'Hello');

		$this->assertSame(['https://catalog-child.example.com'], RuntimeAwareGatewayDouble::$sentBaseUrls);
	}

	public function testSendForUserDelegatesCandidateResolutionAndRetriesFallbacks(): void {
		RuntimeAwareGatewayDouble::$sentBaseUrls = [];
		$appConfig = $this->makeInMemoryAppConfig();
		$gateway = new RuntimeAwareGatewayDouble($appConfig);
		$user = $this->createMock(IUser::class);

		$this->gatewayRoutingService->expects($this->once())
			->method('resolveCandidatesForUser')
			->with($user, 'runtimeaware')
			->willReturn([
				$this->makeCandidate($gateway, 'runtimeaware', 'inst-high', [
					'id' => 'inst-high',
					'label' => 'High',
					'default' => false,
					'createdAt' => '2026-01-02T00:00:00+00:00',
					'config' => ['base_url' => 'https://fail-high.example.com'],
					'isComplete' => true,
					'groupIds' => ['group-b'],
					'priority' => 20,
				]),
				$this->makeCandidate($gateway, 'runtimeaware', 'inst-low', [
					'id' => 'inst-low',
					'label' => 'Low',
					'default' => false,
					'createdAt' => '2026-01-01T00:00:00+00:00',
					'config' => ['base_url' => 'https://ok-low.example.com'],
					'isComplete' => true,
					'groupIds' => ['group-a'],
					'priority' => 10,
				]),
			]);
		$this->logger->expects($this->once())->method('warning');

		$result = $this->service->sendForUser($user, 'runtimeaware', '+5511999990000', 'Hello');

		$this->assertSame(['https://fail-high.example.com', 'https://ok-low.example.com'], RuntimeAwareGatewayDouble::$sentBaseUrls);
		$this->assertSame('inst-low', $result['instanceId']);
	}

	public function testSendForUserFallsBackToGatewayWhenRoutingReturnsNoCandidates(): void {
		RuntimeAwareGatewayDouble::$sentBaseUrls = [];
		$appConfig = $this->makeInMemoryAppConfig();
		$appConfig->setValueString('twofactor_gateway', 'runtimeaware_base_url', 'https://global.example.com');
		$gateway = new RuntimeAwareGatewayDouble($appConfig);
		$user = $this->createMock(IUser::class);

		$this->gatewayRoutingService->expects($this->once())
			->method('resolveCandidatesForUser')
			->with($user, 'runtimeaware')
			->willReturn([]);
		$this->gatewayRoutingService->expects($this->once())
			->method('getGateway')
			->with('runtimeaware')
			->willReturn($gateway);

		$result = $this->service->sendForUser($user, 'runtimeaware', '+5511999990000', 'Hello');

		$this->assertSame(['https://global.example.com'], RuntimeAwareGatewayDouble::$sentBaseUrls);
		$this->assertSame('', $result['instanceId']);
		$this->assertSame('runtimeaware', $result['providerId']);
	}

	public function testEnrichTestResultForReferenceUsesResolvedCandidateConfig(): void {
		/** @var IGateway&ITestResultEnricher&MockObject $gateway */
		$gateway = $this->createMockForIntersectionOfInterfaces([IGateway::class, ITestResultEnricher::class]);
		$gateway->method('getProviderId')->willReturn('gowhatsapp');
		$gateway->method('getSettings')->willReturn(new Settings(name: 'GoWhatsApp', id: 'gowhatsapp', fields: [new FieldDefinition('base_url', 'Base URL')]));
		$gateway->expects($this->once())
			->method('enrichTestResult')
			->with(['base_url' => 'https://wa.example.com'], '+5511999990000')
			->willReturn(['account_name' => 'Acme']);

		$this->gatewayRoutingService->expects($this->once())
			->method('resolveGatewayInstanceReference')
			->with('whatsapp', 'gowhatsapp:prod')
			->willReturn($this->makeCandidate($gateway, 'gowhatsapp', 'gowhatsapp:prod', [
				'id' => 'prod',
				'label' => 'Prod',
				'default' => true,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['base_url' => 'https://wa.example.com'],
				'isComplete' => true,
				'groupIds' => [],
				'priority' => 0,
			]));

		$result = $this->service->enrichTestResultForReference('whatsapp', 'gowhatsapp:prod', '+5511999990000');

		$this->assertSame(['account_name' => 'Acme'], $result);
	}

	/**
	 * @param array{id: string, label: string, default: bool, createdAt: string, config: array<string, string>, isComplete: bool, groupIds: list<string>, priority: int} $instance
	 */
	private function makeCandidate(IGateway $gateway, string $providerId, string $publicInstanceId, array $instance): GatewayRouteCandidate {
		return new GatewayRouteCandidate(
			gateway: $gateway,
			providerId: $providerId,
			publicInstanceId: $publicInstanceId,
			instance: GatewayInstanceRecord::fromArray($instance),
		);
	}
}
