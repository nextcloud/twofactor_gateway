<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Controller;

use OCA\TwoFactorGateway\Controller\AdminGatewayController;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Exception\GatewayInstanceNotFoundException;
use OCA\TwoFactorGateway\Exception\GatewayPermissionDeniedException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\FieldExposure;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IInteractiveSetupGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway;
use OCA\TwoFactorGateway\Provider\Gateway\ITestIdentifierNormalizer;
use OCA\TwoFactorGateway\Provider\Gateway\ITestResultEnricher;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Service\GatewayCatalogService;
use OCA\TwoFactorGateway\Service\GatewayConfigService;
use OCA\TwoFactorGateway\Service\GatewayConfigurationSyncService;
use OCA\TwoFactorGateway\Service\GatewayFieldSanitizer;
use OCA\TwoFactorGateway\Service\GatewayInteractiveSetupSessionService;
use OCA\TwoFactorGateway\Service\GatewayPermissionService;
use OCA\TwoFactorGateway\Service\GatewayViewScope;
use OCP\AppFramework\Http;
use OCP\IAppConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AdminGatewayControllerTest extends TestCase {
	private AdminGatewayController $controller;
	private GatewayCatalogService&MockObject $gatewayCatalogService;
	private GatewayConfigService&MockObject $configService;
	private GatewayFactory&MockObject $gatewayFactory;
	private GatewayConfigurationSyncService&MockObject $gatewayConfigurationSyncService;
	private GatewayInteractiveSetupSessionService&MockObject $gatewayInteractiveSetupSessionService;
	private IGroupManager&MockObject $groupManager;
	private GatewayPermissionService&MockObject $gatewayPermissionService;
	private GatewayViewScope $resolvedViewScope;
	private IUser&MockObject $actor;
	private IUserSession&MockObject $userSession;

	protected function setUp(): void {
		parent::setUp();
		$request = $this->createMock(IRequest::class);
		$this->actor = $this->createMock(IUser::class);
		$this->gatewayCatalogService = $this->createMock(GatewayCatalogService::class);
		$this->configService = $this->createMock(GatewayConfigService::class);
		$this->gatewayFactory = $this->createMock(GatewayFactory::class);
		$this->gatewayConfigurationSyncService = $this->createMock(GatewayConfigurationSyncService::class);
		$this->gatewayInteractiveSetupSessionService = $this->createMock(GatewayInteractiveSetupSessionService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->gatewayPermissionService = $this->createMock(GatewayPermissionService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->resolvedViewScope = GatewayViewScope::ADMIN;
		$this->gatewayConfigurationSyncService->method('syncAfterConfigurationChange');
		$this->actor->method('getUID')->willReturn('admin-user');
		$this->userSession->method('getUser')->willReturn($this->actor);
		$this->gatewayPermissionService->method('resolveViewScope')->willReturnCallback(
			fn (): GatewayViewScope => $this->resolvedViewScope,
		);
		$this->gatewayPermissionService->method('filterVisibleInstances')->willReturnCallback(
			static fn (?IUser $actor, array $instances): array => $instances,
		);
		$this->gatewayCatalogService->method('createInstanceView')->willReturnCallback(
			static fn (?IUser $actor, IGateway $gateway, array $instance): array => $instance,
		);

		$this->controller = new AdminGatewayController(
			$request,
			$this->gatewayCatalogService,
			$this->configService,
			$this->gatewayFactory,
			$this->gatewayConfigurationSyncService,
			new GatewayFieldSanitizer(),
			$this->groupManager,
			$this->gatewayPermissionService,
			$this->gatewayInteractiveSetupSessionService,
			$this->userSession,
		);
	}

	/**
	 * @param list<FieldDefinition>|null $fields
	 */
	private function makeGatewayMock(string $id, ?array $fields = null): IGateway&MockObject {
		$settings = new Settings(
			name: ucfirst($id),
			id: $id,
			fields: $fields ?? [new FieldDefinition('url', 'API URL')],
		);
		$mock = $this->createMock(IGateway::class);
		$mock->method('getProviderId')->willReturn($id);
		$mock->method('getSettings')->willReturn($settings);
		return $mock;
	}

	/**
	 * @param list<FieldDefinition>|null $fields
	 */
	private function makeInteractiveGatewayMock(string $id, ?array $fields = null): IGateway&IInteractiveSetupGateway&MockObject {
		$settings = new Settings(
			name: ucfirst($id),
			id: $id,
			fields: $fields ?? [new FieldDefinition('url', 'API URL')],
		);
		/** @var IGateway&IInteractiveSetupGateway&MockObject $mock */
		$mock = $this->createMockForIntersectionOfInterfaces([IGateway::class, IInteractiveSetupGateway::class]);
		$mock->method('getProviderId')->willReturn($id);
		$mock->method('getSettings')->willReturn($settings);
		return $mock;
	}

	/**
	 * @param list<string> $providerIds
	 */
	private function makeCatalogGatewayMock(string $id, string $selectorField, array $providerIds): IGateway&IProviderCatalogGateway&MockObject {
		$settings = new Settings(
			name: ucfirst($id),
			id: $id,
			fields: [new FieldDefinition('url', 'API URL')],
		);
		/** @var IGateway&IProviderCatalogGateway&MockObject $mock */
		$mock = $this->createMockForIntersectionOfInterfaces([IGateway::class, IProviderCatalogGateway::class]);
		$mock->method('getProviderId')->willReturn($id);
		$mock->method('getSettings')->willReturn($settings);
		$mock->method('getProviderSelectorField')->willReturn(new FieldDefinition($selectorField, 'Provider'));
		$mock->method('getProviderCatalog')->willReturn(array_map(
			static fn (string $providerId): array => [
				'id' => $providerId,
				'name' => ucfirst($providerId),
				'fields' => [],
			],
			$providerIds,
		));
		return $mock;
	}

	public function testListGatewaysReturns200WithData(): void {
		$this->gatewayCatalogService->expects($this->once())
			->method('listGateways')
			->with($this->actor)
			->willReturn([[
				'id' => 'sms',
				'name' => 'Sms',
				'instructions' => null,
				'allowMarkdown' => false,
				'fields' => [],
				'instances' => [],
			]]);

		$response = $this->controller->listGateways();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertCount(1, $data);
		$this->assertSame('sms', $data[0]['id']);
	}

	public function testGetGroupsReturnsSortedAssignableGroups(): void {
		$groupA = $this->createMock(IGroup::class);
		$groupA->method('getGID')->willReturn('admins');
		$groupA->method('getDisplayName')->willReturn('Admins');

		$groupB = $this->createMock(IGroup::class);
		$groupB->method('getGID')->willReturn('alpha');
		$groupB->method('getDisplayName')->willReturn('Alpha');

		$this->groupManager->method('search')->with('', 200, 0)->willReturn([$groupA, $groupB]);
		$this->gatewayPermissionService->expects($this->once())
			->method('filterAssignableGroups')
			->with($this->actor, [$groupA, $groupB])
			->willReturn([$groupA, $groupB]);

		$response = $this->controller->getGroups();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			['id' => 'admins', 'displayName' => 'Admins'],
			['id' => 'alpha', 'displayName' => 'Alpha'],
		], $response->getData());
	}

	public function testGetGroupsAppliesQueryAndLimitBounds(): void {
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn('team-a');
		$group->method('getDisplayName')->willReturn('Team A');

		$this->groupManager->expects($this->once())
			->method('search')
			->with('team', 500, 0)
			->willReturn([$group]);
		$this->gatewayPermissionService->expects($this->once())
			->method('filterAssignableGroups')
			->with($this->actor, [$group])
			->willReturn([$group]);

		$response = $this->controller->getGroups('team', 1000);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			['id' => 'team-a', 'displayName' => 'Team A'],
		], $response->getData());
	}

	public function testGetGroupsFiltersAssignableGroupsForDelegatedActorScope(): void {
		$groupA = $this->createMock(IGroup::class);
		$groupA->method('getGID')->willReturn('admins');
		$groupA->method('getDisplayName')->willReturn('Admins');

		$groupB = $this->createMock(IGroup::class);
		$groupB->method('getGID')->willReturn('alpha');
		$groupB->method('getDisplayName')->willReturn('Alpha');

		$this->groupManager->expects($this->once())
			->method('search')
			->with('', 200, 0)
			->willReturn([$groupA, $groupB]);
		$this->gatewayPermissionService->expects($this->once())
			->method('filterAssignableGroups')
			->with($this->actor, [$groupA, $groupB])
			->willReturn([$groupB]);

		$response = $this->controller->getGroups();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([
			['id' => 'alpha', 'displayName' => 'Alpha'],
		], $response->getData());
	}

	public function testCreateInstanceReturns201OnSuccess(): void {
		$gateway = $this->makeGatewayMock('telegram');
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);
		$this->configService->method('createInstance')
			->with($gateway, 'Prod', ['url' => 'https://example.com'], [], 0, 'admin-user')
			->willReturn(['id' => 'abc123', 'label' => 'Prod', 'default' => true, 'createdAt' => '2026-01-01T00:00:00+00:00', 'config' => ['url' => 'https://example.com'], 'isComplete' => true]);

		$response = $this->controller->createInstance('telegram', 'Prod', ['url' => 'https://example.com']);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame('abc123', $response->getData()['id']);
	}

	public function testCreateInstanceReturns400ForUnknownGateway(): void {
		$this->gatewayFactory->method('get')->willThrowException(new \InvalidArgumentException('Invalid type'));

		$response = $this->controller->createInstance('unknown', 'Test', []);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testCreateInstanceReturns403WhenPermissionServiceRejectsGroupScope(): void {
		$gateway = $this->makeGatewayMock('telegram');
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);
		$this->gatewayPermissionService->expects($this->once())
			->method('assertCanCreateInstanceForGroups')
			->willThrowException(new GatewayPermissionDeniedException('Scope denied'));

		$response = $this->controller->createInstance('telegram', 'Prod', ['url' => 'https://example.com'], ['admins']);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame('Scope denied', $response->getData()['message']);
	}

	public function testCreateInstanceAllowsDelegatedActorToWriteDelegatedFields(): void {
		$this->resolvedViewScope = GatewayViewScope::DELEGATED;
		$gateway = $this->makeGatewayMock('signal', [
			new FieldDefinition(field: 'url', prompt: 'Gateway URL', exposure: FieldExposure::ADMIN),
			new FieldDefinition(field: 'account', prompt: 'Signal account', exposure: FieldExposure::DELEGATED),
		]);
		$this->gatewayFactory->method('get')->with('signal')->willReturn($gateway);
		$this->configService->expects($this->once())
			->method('createInstance')
			->with($gateway, 'Team A', ['account' => '+5511999999999'], ['team-a'], 0, 'admin-user')
			->willReturn([
				'id' => 'delegated-1',
				'label' => 'Team A',
				'default' => true,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['account' => '+5511999999999'],
				'isComplete' => true,
				'groupIds' => ['team-a'],
				'priority' => 0,
			]);

		$response = $this->controller->createInstance('signal', 'Team A', ['account' => '+5511999999999'], ['team-a']);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame(['account' => '+5511999999999'], $response->getData()['config']);
	}

	public function testCreateInstanceReturns403WhenDelegatedActorWritesAdminOnlyFields(): void {
		$this->resolvedViewScope = GatewayViewScope::DELEGATED;
		$gateway = $this->makeGatewayMock('signal', [
			new FieldDefinition(field: 'url', prompt: 'Gateway URL', exposure: FieldExposure::ADMIN),
			new FieldDefinition(field: 'account', prompt: 'Signal account', exposure: FieldExposure::DELEGATED),
		]);
		$this->gatewayFactory->method('get')->with('signal')->willReturn($gateway);
		$this->configService->expects($this->never())->method('createInstance');

		$response = $this->controller->createInstance('signal', 'Team A', [
			'url' => 'https://signal.example.com',
			'account' => '+5511999999999',
		], ['team-a']);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertStringContainsString('url', (string)$response->getData()['message']);
	}

	public function testCreateInstanceKeepsWhatsAppCatalogGatewayWhenProviderIsGoWhatsApp(): void {
		$whatsAppGateway = $this->makeCatalogGatewayMock('whatsapp', 'provider', ['whatsapp', 'gowhatsapp']);
		$this->gatewayFactory->method('get')->willReturnMap([
			['whatsapp', $whatsAppGateway],
		]);
		$this->configService->method('createInstance')
			->with($whatsAppGateway, 'Prod', ['provider' => 'gowhatsapp', 'base_url' => 'https://wa.example.com'], [], 0, 'admin-user')
			->willReturn([
				'id' => 'abc123',
				'label' => 'Prod',
				'default' => true,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['provider' => 'gowhatsapp', 'base_url' => 'https://wa.example.com'],
				'isComplete' => true,
			]);

		$response = $this->controller->createInstance('whatsapp', 'Prod', ['provider' => 'gowhatsapp', 'base_url' => 'https://wa.example.com']);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame('abc123', $response->getData()['id']);
	}

	public function testCreateInstanceKeepsSingleCatalogGatewayWhenPayloadOmitsProvider(): void {
		$whatsAppGateway = $this->makeCatalogGatewayMock('whatsapp', 'provider', ['gowhatsapp']);
		$this->gatewayFactory->method('get')->willReturnMap([
			['whatsapp', $whatsAppGateway],
		]);
		$this->configService->method('createInstance')
			->with($whatsAppGateway, 'Prod', ['base_url' => 'https://wa.example.com'], [], 0, 'admin-user')
			->willReturn([
				'id' => 'abc123',
				'label' => 'Prod',
				'default' => true,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['base_url' => 'https://wa.example.com'],
				'isComplete' => true,
			]);

		$response = $this->controller->createInstance('whatsapp', 'Prod', ['base_url' => 'https://wa.example.com']);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame('abc123', $response->getData()['id']);
	}

	public function testCreateInstanceKeepsCatalogGatewayWhenProviderResolvesToNonGatewayObject(): void {
		$telegramGateway = $this->makeCatalogGatewayMock('telegram', 'provider', ['telegram_bot', 'telegram_client']);
		$providerDriver = new \stdClass();
		$this->gatewayFactory->method('get')->willReturnMap([
			['telegram', $telegramGateway],
			['telegram_bot', $providerDriver],
		]);
		$this->configService->method('createInstance')
			->with($telegramGateway, 'Prod', ['provider' => 'telegram_bot', 'bot_token' => 'secret'], [], 0, 'admin-user')
			->willReturn([
				'id' => 'tg123',
				'label' => 'Prod',
				'default' => true,
				'createdAt' => '2026-01-01T00:00:00+00:00',
				'config' => ['provider' => 'telegram_bot', 'bot_token' => 'secret'],
				'isComplete' => true,
			]);

		$response = $this->controller->createInstance('telegram', 'Prod', ['provider' => 'telegram_bot', 'bot_token' => 'secret']);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame('tg123', $response->getData()['id']);
	}

	public function testGetInstanceReturns200WithInstanceData(): void {
		$gateway = $this->makeGatewayMock('sms');
		$this->gatewayFactory->method('get')->with('sms')->willReturn($gateway);
		$record = ['id' => 'def456', 'label' => 'Test', 'default' => false, 'createdAt' => '2026-01-01T00:00:00+00:00', 'config' => ['url' => 'https://sms.example.com'], 'isComplete' => true];
		$this->configService->method('getInstance')->with($gateway, 'def456')->willReturn($record);

		$response = $this->controller->getInstance('sms', 'def456');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('def456', $response->getData()['id']);
	}

	public function testGetInstanceReturns404WhenNotFound(): void {
		$gateway = $this->makeGatewayMock('sms');
		$this->gatewayFactory->method('get')->with('sms')->willReturn($gateway);
		$this->configService->method('getInstance')
			->willThrowException(new GatewayInstanceNotFoundException('sms', 'notfound'));

		$response = $this->controller->getInstance('sms', 'notfound');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testGetInstanceReturns403WhenPermissionServiceRejectsView(): void {
		$gateway = $this->makeGatewayMock('sms');
		$this->gatewayFactory->method('get')->with('sms')->willReturn($gateway);
		$this->configService->method('getInstance')->with($gateway, 'def456')->willReturn([
			'id' => 'def456',
			'label' => 'Test',
			'default' => false,
			'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['url' => 'https://sms.example.com'],
			'isComplete' => true,
		]);
		$this->gatewayPermissionService->expects($this->once())
			->method('assertCanViewInstance')
			->willThrowException(new GatewayPermissionDeniedException('Forbidden'));

		$response = $this->controller->getInstance('sms', 'def456');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame('Forbidden', $response->getData()['message']);
	}

	public function testGetInstanceResolvesGoWhatsAppPrefixedInstanceId(): void {
		$whatsAppGateway = $this->makeCatalogGatewayMock('whatsapp', 'provider', ['whatsapp', 'gowhatsapp']);
		$goGateway = $this->makeGatewayMock('gowhatsapp');
		$this->gatewayFactory->method('get')->willReturnMap([
			['whatsapp', $whatsAppGateway],
			['gowhatsapp', $goGateway],
		]);
		$record = ['id' => 'def456', 'label' => 'GoWA', 'default' => false, 'createdAt' => '2026-01-01T00:00:00+00:00', 'config' => ['base_url' => 'https://wa.example.com'], 'isComplete' => true];
		$this->configService->method('getInstance')->with($goGateway, 'def456')->willReturn($record);

		$response = $this->controller->getInstance('whatsapp', 'gowhatsapp:def456');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('def456', $response->getData()['id']);
	}

	public function testUpdateInstanceReturns200OnSuccess(): void {
		$gateway = $this->makeGatewayMock('signal');
		$this->gatewayFactory->method('get')->with('signal')->willReturn($gateway);
		$record = ['id' => 'abc', 'label' => 'New', 'default' => true, 'createdAt' => '2026-01-01T00:00:00+00:00', 'config' => ['url' => 'https://signal.example.com'], 'isComplete' => true];
		$this->configService->method('updateInstance')
			->with($gateway, 'abc', 'New', ['url' => 'https://signal.example.com'], [], 0)
			->willReturn($record);

		$response = $this->controller->updateInstance('signal', 'abc', 'New', ['url' => 'https://signal.example.com']);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateInstanceReturns404WhenNotFound(): void {
		$gateway = $this->makeGatewayMock('signal');
		$this->gatewayFactory->method('get')->with('signal')->willReturn($gateway);
		$this->configService->method('updateInstance')
			->willThrowException(new GatewayInstanceNotFoundException('signal', 'notfound'));

		$response = $this->controller->updateInstance('signal', 'notfound', 'X', []);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUpdateInstanceReturns403WhenDelegatedActorWritesAdminOnlyFields(): void {
		$this->resolvedViewScope = GatewayViewScope::DELEGATED;
		$gateway = $this->makeGatewayMock('signal', [
			new FieldDefinition(field: 'url', prompt: 'Gateway URL', exposure: FieldExposure::ADMIN),
			new FieldDefinition(field: 'account', prompt: 'Signal account', exposure: FieldExposure::DELEGATED),
		]);
		$this->gatewayFactory->method('get')->with('signal')->willReturn($gateway);
		$this->configService->method('getInstance')->with($gateway, 'abc')->willReturn([
			'id' => 'abc',
			'label' => 'Team A',
			'default' => true,
			'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => [
				'url' => 'https://signal.example.com',
				'account' => '+5511988887777',
			],
			'isComplete' => true,
			'groupIds' => ['team-a'],
			'priority' => 10,
		]);
		$this->configService->expects($this->never())->method('updateInstance');

		$response = $this->controller->updateInstance('signal', 'abc', 'Team A', [
			'url' => 'https://signal-2.example.com',
			'account' => '+5511999999999',
		], ['team-a'], 10);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertStringContainsString('url', (string)$response->getData()['message']);
	}

	public function testUpdateInstanceReturns200EvenIfSessionMonitorSyncFails(): void {
		$gateway = $this->makeGatewayMock('signal');
		$this->gatewayFactory->method('get')->with('signal')->willReturn($gateway);
		$record = ['id' => 'abc', 'label' => 'New', 'default' => true, 'createdAt' => '2026-01-01T00:00:00+00:00', 'config' => ['url' => 'https://signal.example.com'], 'isComplete' => true];
		$this->configService->method('updateInstance')
			->with($gateway, 'abc', 'New', ['url' => 'https://signal.example.com'], [], 0)
			->willReturn($record);
		$this->gatewayConfigurationSyncService->method('syncAfterConfigurationChange')
			->willThrowException(new \InvalidArgumentException('Invalid type <gowhatsapp>'));

		$response = $this->controller->updateInstance('signal', 'abc', 'New', ['url' => 'https://signal.example.com']);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('abc', $response->getData()['id']);
	}

	public function testDeleteInstanceReturns200OnSuccess(): void {
		$gateway = $this->makeGatewayMock('xmpp');
		$this->gatewayFactory->method('get')->with('xmpp')->willReturn($gateway);
		$this->configService->expects($this->once())->method('deleteInstance')->with($gateway, 'ghi789');

		$response = $this->controller->deleteInstance('xmpp', 'ghi789');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testDeleteInstanceReturns404WhenNotFound(): void {
		$gateway = $this->makeGatewayMock('xmpp');
		$this->gatewayFactory->method('get')->with('xmpp')->willReturn($gateway);
		$this->configService->method('deleteInstance')
			->willThrowException(new GatewayInstanceNotFoundException('xmpp', 'notfound'));

		$response = $this->controller->deleteInstance('xmpp', 'notfound');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testSetDefaultInstanceReturns200OnSuccess(): void {
		$gateway = $this->makeGatewayMock('sms');
		$this->gatewayFactory->method('get')->with('sms')->willReturn($gateway);
		$this->configService->expects($this->once())->method('setDefaultInstance')->with($gateway, 'abc123');

		$response = $this->controller->setDefaultInstance('sms', 'abc123');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testSetDefaultInstanceReturns404WhenNotFound(): void {
		$gateway = $this->makeGatewayMock('sms');
		$this->gatewayFactory->method('get')->with('sms')->willReturn($gateway);
		$this->configService->method('setDefaultInstance')
			->willThrowException(new GatewayInstanceNotFoundException('sms', 'notfound'));

		$response = $this->controller->setDefaultInstance('sms', 'notfound');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testTestInstanceReturns200OnSuccessfulSend(): void {
		$gateway = $this->makeGatewayMock('telegram');
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);
		$record = [
			'id' => 'abc', 'label' => 'Prod', 'default' => true, 'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['url' => 'https://t.example.com'], 'isComplete' => true,
		];
		$this->configService->method('getInstance')->with($gateway, 'abc')->willReturn($record);
		$gateway->expects($this->once())->method('send')->with('+1234567890', 'Two Factor Gateway test message');

		$response = $this->controller->testInstance('telegram', 'abc', '+1234567890');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['success']);
		$this->assertArrayNotHasKey('accountInfo', $response->getData());
	}

	public function testTestInstanceIncludesAccountInfoWhenEnricherReturnsData(): void {
		/** @var IGateway&ITestResultEnricher&MockObject $gateway */
		$gateway = $this->createMockForIntersectionOfInterfaces([IGateway::class, ITestResultEnricher::class]);
		$settings = new Settings(name: 'GoWhatsApp', id: 'gowhatsapp', fields: [new FieldDefinition('base_url', 'Base URL')]);
		$gateway->method('getProviderId')->willReturn('gowhatsapp');
		$gateway->method('getSettings')->willReturn($settings);

		$this->gatewayFactory->method('get')->with('gowhatsapp')->willReturn($gateway);
		$instanceConfig = ['base_url' => 'http://wa.example.com', 'phone' => '5511999990000'];
		$record = [
			'id' => 'def', 'label' => 'WA Prod', 'default' => true, 'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => $instanceConfig, 'isComplete' => true,
		];
		$this->configService->method('getInstance')->with($gateway, 'def')->willReturn($record);
		$gateway->expects($this->once())->method('send');
		$gateway->expects($this->once())->method('enrichTestResult')
			->with($instanceConfig, '+5511999990000')
			->willReturn(['account_name' => 'Acme Corp']);

		$response = $this->controller->testInstance('gowhatsapp', 'def', '+5511999990000');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['success']);
		$this->assertSame(['account_name' => 'Acme Corp'], $response->getData()['accountInfo']);
	}

	public function testTestInstanceOmitsAccountInfoWhenEnricherReturnsEmpty(): void {
		/** @var IGateway&ITestResultEnricher&MockObject $gateway */
		$gateway = $this->createMockForIntersectionOfInterfaces([IGateway::class, ITestResultEnricher::class]);
		$settings = new Settings(name: 'GoWhatsApp', id: 'gowhatsapp', fields: [new FieldDefinition('base_url', 'Base URL')]);
		$gateway->method('getProviderId')->willReturn('gowhatsapp');
		$gateway->method('getSettings')->willReturn($settings);

		$this->gatewayFactory->method('get')->with('gowhatsapp')->willReturn($gateway);
		$record = [
			'id' => 'ghi', 'label' => 'WA Dev', 'default' => false, 'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['base_url' => 'http://wa.example.com'], 'isComplete' => true,
		];
		$this->configService->method('getInstance')->with($gateway, 'ghi')->willReturn($record);
		$gateway->method('send');
		$gateway->expects($this->once())->method('enrichTestResult')->with(['base_url' => 'http://wa.example.com'], '+5511999990000')->willReturn([]);

		$response = $this->controller->testInstance('gowhatsapp', 'ghi', '+5511999990000');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertArrayNotHasKey('accountInfo', $response->getData());
	}

	public function testTestInstanceReturnsSafeMessageForUnexpectedProviderFailures(): void {
		$gateway = $this->makeGatewayMock('telegram');
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);
		$record = [
			'id' => 'abc', 'label' => 'Prod', 'default' => true, 'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['url' => 'https://t.example.com'], 'isComplete' => true,
		];
		$this->configService->method('getInstance')->with($gateway, 'abc')->willReturn($record);
		$gateway->expects($this->once())->method('send')->willThrowException(new \RuntimeException('boom'));

		$response = $this->controller->testInstance('telegram', 'abc', '+1234567890');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame([
			'success' => false,
			'message' => 'Gateway test failed unexpectedly.',
		], $response->getData());
	}

	public function testTestInstanceReturns400WhenNotComplete(): void {
		$gateway = $this->makeGatewayMock('telegram');
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);
		$record = [
			'id' => 'abc', 'label' => 'Prod', 'default' => false, 'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => [], 'isComplete' => false,
		];
		$this->configService->method('getInstance')->with($gateway, 'abc')->willReturn($record);

		$response = $this->controller->testInstance('telegram', 'abc', '+1234567890');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testTestInstanceReturns400WhenGatewayConfigurationIsInvalid(): void {
		$gateway = $this->makeGatewayMock('telegram');
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);
		$record = [
			'id' => 'abc', 'label' => 'Prod', 'default' => false, 'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['provider' => 'telegram_bot'], 'isComplete' => true,
		];
		$this->configService->method('getInstance')->with($gateway, 'abc')->willReturn($record);
		$gateway->method('send')->willThrowException(new ConfigurationException('Invalid gateway/provider configuration set'));

		$response = $this->controller->testInstance('telegram', 'abc', '+1234567890');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertFalse($response->getData()['success']);
	}

	public function testTestInstanceUsesInstanceRuntimeConfigWhenGatewaySupportsIt(): void {
		$gateway = new RuntimeConfigAwareGatewayTestDouble($this->createMock(IAppConfig::class));
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);
		$record = [
			'id' => 'abc', 'label' => 'Prod', 'default' => true, 'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['provider' => 'telegram_bot'], 'isComplete' => true,
		];
		$this->configService->method('getInstance')->with($gateway, 'abc')->willReturn($record);

		$response = $this->controller->testInstance('telegram', 'abc', 'vitormattos');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['success']);
	}

	public function testTestInstanceUsesGatewayIdentifierNormalizerWhenAvailable(): void {
		/** @var IGateway&ITestIdentifierNormalizer&MockObject $gateway */
		$gateway = $this->createMockForIntersectionOfInterfaces([IGateway::class, ITestIdentifierNormalizer::class]);
		$settings = new Settings(
			name: 'Telegram',
			id: 'telegram',
			fields: [new FieldDefinition('url', 'API URL')],
		);
		$gateway->method('getProviderId')->willReturn('telegram');
		$gateway->method('getSettings')->willReturn($settings);
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);
		$record = [
			'id' => 'abc', 'label' => 'Prod', 'default' => true, 'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['provider' => 'telegram_client'], 'isComplete' => true,
		];
		$this->configService->method('getInstance')->with($gateway, 'abc')->willReturn($record);
		$gateway->expects($this->once())->method('normalizeTestIdentifier')->with('vitormattos')->willReturn('@vitormattos');
		$gateway->expects($this->once())->method('send')->with('@vitormattos', 'Two Factor Gateway test message');

		$response = $this->controller->testInstance('telegram', 'abc', 'vitormattos');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['success']);
	}

	public function testTestInstanceKeepsTelegramNumericIdentifierUnchanged(): void {
		/** @var IGateway&ITestIdentifierNormalizer&MockObject $gateway */
		$gateway = $this->createMockForIntersectionOfInterfaces([IGateway::class, ITestIdentifierNormalizer::class]);
		$settings = new Settings(
			name: 'Telegram',
			id: 'telegram',
			fields: [new FieldDefinition('url', 'API URL')],
		);
		$gateway->method('getProviderId')->willReturn('telegram');
		$gateway->method('getSettings')->willReturn($settings);
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);
		$record = [
			'id' => 'abc', 'label' => 'Prod', 'default' => true, 'createdAt' => '2026-01-01T00:00:00+00:00',
			'config' => ['provider' => 'telegram_client'], 'isComplete' => true,
		];
		$this->configService->method('getInstance')->with($gateway, 'abc')->willReturn($record);
		$gateway->expects($this->once())->method('normalizeTestIdentifier')->with('-1001234567890')->willReturn('-1001234567890');
		$gateway->expects($this->once())->method('send')->with('-1001234567890', 'Two Factor Gateway test message');

		$response = $this->controller->testInstance('telegram', 'abc', '-1001234567890');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['success']);
	}

	public function testTestInstanceReturns404WhenNotFound(): void {
		$gateway = $this->makeGatewayMock('telegram');
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);
		$this->configService->method('getInstance')
			->willThrowException(new GatewayInstanceNotFoundException('telegram', 'nope'));

		$response = $this->controller->testInstance('telegram', 'nope', '+1234567890');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testStartInteractiveSetupReturns400WhenGatewayDoesNotSupportIt(): void {
		$gateway = $this->makeGatewayMock('telegram');
		$this->gatewayFactory->method('get')->with('telegram')->willReturn($gateway);

		$response = $this->controller->startInteractiveSetup('telegram', ['base_url' => 'https://wa.example.com']);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testStartInteractiveSetupReturns403WhenDelegatedActorSuppliesAdminOnlySetupField(): void {
		$gateway = $this->makeInteractiveGatewayMock('signal', [
			new FieldDefinition(field: 'url', prompt: 'Gateway URL', exposure: FieldExposure::ADMIN),
			new FieldDefinition(field: 'account', prompt: 'Signal account', exposure: FieldExposure::DELEGATED),
		]);
		$this->resolvedViewScope = GatewayViewScope::DELEGATED;
		$gateway->expects($this->never())->method('interactiveSetupStart');
		$this->gatewayFactory->method('get')->with('signal')->willReturn($gateway);

		$response = $this->controller->startInteractiveSetup('signal', ['url' => 'http://signal.internal']);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertStringContainsString('url', (string)$response->getData()['message']);
	}

	public function testStartInteractiveSetupReturns200ForInteractiveGateway(): void {
		$gateway = $this->makeInteractiveGatewayMock('gowhatsapp');
		$gateway->method('interactiveSetupStart')->with(['base_url' => 'https://wa.example.com'])->willReturn([
			'status' => 'needs_input',
			'sessionId' => 'abc',
			'step' => 'phone',
		]);
		$this->gatewayInteractiveSetupSessionService->expects($this->once())
			->method('claim')
			->with($this->actor, 'gowhatsapp', 'abc');
		$this->gatewayFactory->method('get')->with('gowhatsapp')->willReturn($gateway);

		$response = $this->controller->startInteractiveSetup('gowhatsapp', ['base_url' => 'https://wa.example.com']);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('needs_input', $response->getData()['status']);
	}

	public function testInteractiveSetupStepReturns200ForInteractiveGateway(): void {
		$gateway = $this->makeInteractiveGatewayMock('gowhatsapp');
		$this->gatewayInteractiveSetupSessionService->expects($this->once())
			->method('assertCanAccess')
			->with($this->actor, 'gowhatsapp', 'session-1');
		$this->gatewayInteractiveSetupSessionService->expects($this->never())
			->method('release');
		$gateway->method('interactiveSetupStep')
			->with('session-1', 'poll_pairing', [])
			->willReturn([
				'status' => 'pending',
				'sessionId' => 'session-1',
				'step' => 'pairing',
			]);
		$this->gatewayFactory->method('get')->with('gowhatsapp')->willReturn($gateway);

		$response = $this->controller->interactiveSetupStep('gowhatsapp', 'session-1', 'poll_pairing', []);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('pending', $response->getData()['status']);
	}

	public function testCancelInteractiveSetupReturns200ForInteractiveGateway(): void {
		$gateway = $this->makeInteractiveGatewayMock('gowhatsapp');
		$this->gatewayInteractiveSetupSessionService->expects($this->once())
			->method('assertCanAccess')
			->with($this->actor, 'gowhatsapp', 'session-1');
		$this->gatewayInteractiveSetupSessionService->expects($this->once())
			->method('release')
			->with('gowhatsapp', 'session-1');
		$gateway->method('interactiveSetupCancel')->with('session-1')->willReturn([
			'status' => 'cancelled',
		]);
		$this->gatewayFactory->method('get')->with('gowhatsapp')->willReturn($gateway);

		$response = $this->controller->cancelInteractiveSetup('gowhatsapp', 'session-1');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('cancelled', $response->getData()['status']);
	}

	public function testInteractiveSetupStepReturns403WhenSessionBelongsToAnotherActor(): void {
		$gateway = $this->makeInteractiveGatewayMock('gowhatsapp');
		$this->gatewayInteractiveSetupSessionService->expects($this->once())
			->method('assertCanAccess')
			->with($this->actor, 'gowhatsapp', 'session-1')
			->willThrowException(new GatewayPermissionDeniedException('Session denied'));
		$gateway->expects($this->never())->method('interactiveSetupStep');
		$this->gatewayFactory->method('get')->with('gowhatsapp')->willReturn($gateway);

		$response = $this->controller->interactiveSetupStep('gowhatsapp', 'session-1', 'poll_pairing', []);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame('Session denied', $response->getData()['message']);
	}

	public function testInteractiveSetupStepReleasesSessionWhenGatewayFinishesSetup(): void {
		$gateway = $this->makeInteractiveGatewayMock('gowhatsapp');
		$this->gatewayInteractiveSetupSessionService->expects($this->once())
			->method('assertCanAccess')
			->with($this->actor, 'gowhatsapp', 'session-1');
		$this->gatewayInteractiveSetupSessionService->expects($this->once())
			->method('release')
			->with('gowhatsapp', 'session-1');
		$gateway->method('interactiveSetupStep')
			->with('session-1', 'poll_pairing', [])
			->willReturn([
				'status' => 'done',
			]);
		$this->gatewayFactory->method('get')->with('gowhatsapp')->willReturn($gateway);

		$response = $this->controller->interactiveSetupStep('gowhatsapp', 'session-1', 'poll_pairing', []);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('done', $response->getData()['status']);
	}

	public function testCancelInteractiveSetupReturns403WhenSessionBelongsToAnotherActor(): void {
		$gateway = $this->makeInteractiveGatewayMock('gowhatsapp');
		$this->gatewayInteractiveSetupSessionService->expects($this->once())
			->method('assertCanAccess')
			->with($this->actor, 'gowhatsapp', 'session-1')
			->willThrowException(new GatewayPermissionDeniedException('Session denied'));
		$gateway->expects($this->never())->method('interactiveSetupCancel');
		$this->gatewayFactory->method('get')->with('gowhatsapp')->willReturn($gateway);

		$response = $this->controller->cancelInteractiveSetup('gowhatsapp', 'session-1');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame('Session denied', $response->getData()['message']);
	}
}

class RuntimeConfigAwareGatewayTestDouble extends \OCA\TwoFactorGateway\Provider\Gateway\AGateway {
	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		$provider = $this->runtimeConfig['provider'] ?? '';
		if ($provider !== 'telegram_bot') {
			throw new ConfigurationException('Invalid gateway/provider configuration set');
		}
	}

	#[\Override]
	public function createSettings(): Settings {
		return new Settings(
			name: 'Telegram',
			id: 'telegram',
			fields: [new FieldDefinition('provider', 'Provider')],
		);
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		return 0;
	}
}
