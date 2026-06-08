<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Controller;

use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Exception\GatewayInstanceNotFoundException;
use OCA\TwoFactorGateway\Exception\GatewayPermissionDeniedException;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IInteractiveSetupGateway;
use OCA\TwoFactorGateway\Provider\Gateway\ITestIdentifierNormalizer;
use OCA\TwoFactorGateway\Provider\Gateway\ITestResultEnricher;
use OCA\TwoFactorGateway\Service\GatewayAdminScreenService;
use OCA\TwoFactorGateway\Service\GatewayCatalogService;
use OCA\TwoFactorGateway\Service\GatewayConfigService;
use OCA\TwoFactorGateway\Service\GatewayConfigurationSyncService;
use OCA\TwoFactorGateway\Service\GatewayFieldSanitizer;
use OCA\TwoFactorGateway\Service\GatewayInteractiveSetupSessionService;
use OCA\TwoFactorGateway\Service\GatewayPermissionService;
use OCA\TwoFactorGateway\Service\GatewayViewScope;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

class AdminGatewayController extends OCSController {
	public function __construct(
		IRequest $request,
		private GatewayAdminScreenService $gatewayAdminScreenService,
		private GatewayCatalogService $gatewayCatalogService,
		private GatewayConfigService $configService,
		private GatewayFactory $gatewayFactory,
		private GatewayConfigurationSyncService $gatewayConfigurationSyncService,
		private GatewayFieldSanitizer $gatewayFieldSanitizer,
		private IGroupManager $groupManager,
		private GatewayPermissionService $gatewayPermissionService,
		private GatewayInteractiveSetupSessionService $gatewayInteractiveSetupSessionService,
		private IUserSession $userSession,
	) {
		parent::__construct('twofactor_gateway', $request);
	}

	/**
	 * List all available gateways with current configuration instances
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: OK
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'GET', url: '/admin/gateways')]
	public function listGateways(): DataResponse {
		return new DataResponse($this->gatewayCatalogService->listGateways($this->currentActor()));
	}

	/**
	 * List a screen-ready admin payload with gateways, assignable groups and flattened display items
	 *
	 * @param int $groupLimit Maximum number of groups returned (bounded server-side)
	 *
	 * @return DataResponse<Http::STATUS_OK, array{
	 *   gateways: list<array<string, mixed>>,
	 *   groups: list<array{id: string, displayName: string}>,
	 *   allowedActions: array{
	 *     canView: bool,
	 *     canCreateInstances: bool,
	 *     canEditInstances: bool,
	 *     canDeleteInstances: bool,
	 *     canSetDefaultInstances: bool,
	 *     canManageRouting: bool,
	 *     canTestInstances: bool,
	 *     canReorderInstances: bool
	 *   },
	 *   items: list<array{
	 *     orderKey: string,
	 *     gatewayId: string,
	 *     providerName: string,
	 *     fields: list<array<string, mixed>>,
	 *     instance: array<string, mixed>,
	 *     groupNames: list<string>,
	 *     showRoutingAction: bool
	 *   }>
	 * }, array{}>
	 *
	 * 200: OK
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'GET', url: '/admin/screen')]
	public function getScreen(int $groupLimit = 200): DataResponse {
		return new DataResponse($this->gatewayAdminScreenService->build($this->currentActor(), $groupLimit));
	}

	/**
	 * List assignable Nextcloud groups for per-instance routing
	 *
	 * @param string $query Optional search term used to filter groups server-side
	 * @param int $limit Maximum number of groups returned (bounded server-side)
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array{id: string, displayName: string}>, array{}>
	 *
	 * 200: OK
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'GET', url: '/admin/groups')]
	public function getGroups(string $query = '', int $limit = 200): DataResponse {
		$limit = max(1, min(500, $limit));
		$actor = $this->currentActor();
		$matchingGroups = $this->groupManager->search($query, $limit, 0);
		$assignableGroups = $this->gatewayPermissionService->filterAssignableGroups($actor, $matchingGroups);
		$groups = array_map(
			static fn ($group): array => [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			],
			$assignableGroups,
		);

		usort($groups, static fn (array $left, array $right): int => strcasecmp($left['displayName'], $right['displayName']));

		return new DataResponse($groups);
	}

	/**
	 * Create a new configuration instance for a gateway
	 *
	 * @param string $gateway The gateway id (e.g. "sms", "telegram")
	 * @param string $label Human-readable name for this instance
	 * @param array<string, string> $config Field values
	 * @param list<string> $groupIds Optional group restrictions for routing
	 * @param int $priority Optional routing priority (higher runs first)
	 *
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{message: string}, array{}>
	 *
	 * 201: Created
	 * 400: Unknown gateway
	 * 403: Permission denied for requested group scope
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'POST', url: '/admin/gateways/{gateway}/instances')]
	public function createInstance(string $gateway, string $label, array $config = [], array $groupIds = [], int $priority = 0): DataResponse {
		$actor = $this->currentActor();

		try {
			$gw = $this->resolveGatewayForConfigurationPayload($gateway, $config);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->gatewayPermissionService->assertCanCreateInstanceForGroups($actor, $groupIds);
			$this->assertCanWriteConfigurationFields($actor, $gw, $config);
		} catch (GatewayPermissionDeniedException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}

		$instance = $this->configService->createInstance($gw, $label, $config, $groupIds, $priority, $actor?->getUID());
		$this->syncSessionMonitorSafely($gw);
		return new DataResponse(
			$this->gatewayCatalogService->createInstanceView($actor, $gw, $instance),
			Http::STATUS_CREATED,
		);
	}

	/**
	 * Get a single configuration instance
	 *
	 * @param string $gateway The gateway id
	 * @param string $instanceId The instance id
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Instance not found
	 * 400: Unknown gateway
	 * 403: Permission denied for this instance
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'GET', url: '/admin/gateways/{gateway}/instances/{instanceId}')]
	public function getInstance(string $gateway, string $instanceId): DataResponse {
		$actor = $this->currentActor();

		try {
			$gw = $this->resolveGatewayForInstance($gateway, $instanceId);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$instance = $this->configService->getInstance($gw, $instanceId);
			$this->gatewayPermissionService->assertCanViewInstance($actor, $instance);
			return new DataResponse($this->gatewayCatalogService->createInstanceView($actor, $gw, $instance));
		} catch (GatewayInstanceNotFoundException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (GatewayPermissionDeniedException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Update an existing configuration instance
	 *
	 * @param string $gateway The gateway id
	 * @param string $instanceId The instance id
	 * @param string $label Updated label
	 * @param array<string, string> $config Updated field values
	 * @param list<string> $groupIds Updated group restrictions for routing
	 * @param int $priority Updated routing priority
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Instance not found
	 * 400: Unknown gateway
	 * 403: Permission denied for this instance or target group scope
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'PUT', url: '/admin/gateways/{gateway}/instances/{instanceId}')]
	public function updateInstance(string $gateway, string $instanceId, string $label, array $config = [], array $groupIds = [], int $priority = 0): DataResponse {
		$actor = $this->currentActor();

		try {
			$gw = $this->resolveGatewayForUpdate($gateway, $instanceId, $config);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$existing = $this->configService->getInstance($gw, $instanceId);
			$this->gatewayPermissionService->assertCanEditInstance($actor, $existing);
			$this->gatewayPermissionService->assertCanCreateInstanceForGroups($actor, $groupIds);
			$this->assertCanWriteConfigurationFields($actor, $gw, $config);
			$record = $this->configService->updateInstance($gw, $instanceId, $label, $config, $groupIds, $priority);
			$this->syncSessionMonitorSafely($gw);
			return new DataResponse($this->gatewayCatalogService->createInstanceView($actor, $gw, $record));
		} catch (GatewayInstanceNotFoundException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (GatewayPermissionDeniedException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Delete a configuration instance
	 *
	 * @param string $gateway The gateway id
	 * @param string $instanceId The instance id
	 *
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Instance not found
	 * 400: Unknown gateway
	 * 403: Permission denied for this instance
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'DELETE', url: '/admin/gateways/{gateway}/instances/{instanceId}')]
	public function deleteInstance(string $gateway, string $instanceId): DataResponse {
		$actor = $this->currentActor();

		try {
			$gw = $this->resolveGatewayForInstance($gateway, $instanceId);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$instance = $this->configService->getInstance($gw, $instanceId);
			$this->gatewayPermissionService->assertCanDeleteInstance($actor, $instance);
			$this->configService->deleteInstance($gw, $instanceId);
			$this->syncSessionMonitorSafely($gw);
			return new DataResponse([]);
		} catch (GatewayInstanceNotFoundException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (GatewayPermissionDeniedException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * Promote an instance to be the default for its gateway
	 *
	 * The instance's field values are mirrored to the primary config keys used by
	 * the 2-FA flow and CLI commands.
	 *
	 * @param string $gateway The gateway id
	 * @param string $instanceId The instance id to promote
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Instance not found
	 * 400: Unknown gateway
	 * 403: Permission denied for routing changes
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'POST', url: '/admin/gateways/{gateway}/instances/{instanceId}/default')]
	public function setDefaultInstance(string $gateway, string $instanceId): DataResponse {
		$actor = $this->currentActor();

		try {
			$gw = $this->resolveGatewayForInstance($gateway, $instanceId);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$instance = $this->configService->getInstance($gw, $instanceId);
			$this->gatewayPermissionService->assertCanManageRouting($actor, $instance);
			$this->configService->setDefaultInstance($gw, $instanceId);
			$this->syncSessionMonitorSafely($gw);
			return new DataResponse([]);
		} catch (GatewayInstanceNotFoundException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (GatewayPermissionDeniedException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	private function syncSessionMonitorSafely(IGateway $gateway): void {
		try {
			$this->gatewayConfigurationSyncService->syncAfterConfigurationChange($gateway);
		} catch (\Throwable) {
			// Sync failures must not break admin CRUD/test operations.
		}
	}

	/**
	 * Send a test message using a specific configuration instance
	 *
	 * @param string $gateway The gateway id
	 * @param string $instanceId The instance id to test
	 * @param string $identifier The recipient identifier (e.g. phone number)
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array<string, mixed>, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{message: string}, array{}>
	 *
	 * 200: Test sent
	 * 400: Gateway not complete or unknown gateway
	 * 404: Instance not found
	 * 403: Permission denied for this instance
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'POST', url: '/admin/gateways/{gateway}/instances/{instanceId}/test')]
	public function testInstance(string $gateway, string $instanceId, string $identifier): DataResponse {
		$identifier = trim($identifier);
		$actor = $this->currentActor();

		try {
			$gw = $this->resolveGatewayForInstance($gateway, $instanceId);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$instance = $this->configService->getInstance($gw, $instanceId);
			$this->gatewayPermissionService->assertCanViewInstance($actor, $instance);
		} catch (GatewayInstanceNotFoundException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (GatewayPermissionDeniedException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}

		if (!$instance['isComplete']) {
			return new DataResponse(['message' => 'Gateway instance is not fully configured.'], Http::STATUS_BAD_REQUEST);
		}

		$gatewayForTest = $gw;
		$instanceConfig = is_array($instance['config'] ?? null) ? $instance['config'] : [];
		if ($instanceConfig !== [] && method_exists($gw, 'withRuntimeConfig')) {
			$runtimeGateway = $gw->withRuntimeConfig($instanceConfig);
			if ($runtimeGateway instanceof IGateway) {
				$gatewayForTest = $runtimeGateway;
			}
		}

		if ($gatewayForTest instanceof ITestIdentifierNormalizer) {
			$identifier = $gatewayForTest->normalizeTestIdentifier($identifier);
		}

		try {
			$gatewayForTest->send($identifier, 'Two Factor Gateway test message');
			$data = ['success' => true, 'message' => 'Test message sent successfully.'];

			$gatewayForEnrichment = null;
			if ($gatewayForTest instanceof ITestResultEnricher) {
				$gatewayForEnrichment = $gatewayForTest;
			} elseif ($gw instanceof ITestResultEnricher) {
				$gatewayForEnrichment = $gw;
				if ($instanceConfig !== [] && method_exists($gw, 'withRuntimeConfig')) {
					$runtimeEnricher = $gw->withRuntimeConfig($instanceConfig);
					if ($runtimeEnricher instanceof ITestResultEnricher) {
						$gatewayForEnrichment = $runtimeEnricher;
					}
				}
			}

			if ($gatewayForEnrichment instanceof ITestResultEnricher) {
				$accountInfo = $gatewayForEnrichment->enrichTestResult($instanceConfig, $identifier);
				if ($accountInfo !== []) {
					$data['accountInfo'] = $accountInfo;
				}
			}
			return new DataResponse($data);
		} catch (MessageTransmissionException|ConfigurationException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable) {
			return new DataResponse(['success' => false, 'message' => 'Gateway test failed unexpectedly.'], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Start an interactive setup flow for gateways that support it
	 *
	 * @param string $gateway The gateway id
	 * @param array<string, string> $input Initial setup input
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{message: string}, array{}>
	 *
	 * 200: Interactive setup started
	 * 400: Unknown gateway, invalid provider, or interactive setup unsupported
	 * 403: Permission denied for admin-only setup fields
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'POST', url: '/admin/gateways/{gateway}/interactive-setup/start')]
	public function startInteractiveSetup(string $gateway, array $input = []): DataResponse {
		try {
			$gw = $this->resolveGatewayForPayload($gateway, $input);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		if (!($gw instanceof IInteractiveSetupGateway)) {
			return new DataResponse(['message' => 'Gateway does not support interactive setup.'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->assertCanWriteConfigurationFields($this->currentActor(), $gw, $input);
		} catch (GatewayPermissionDeniedException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}

		$response = $gw->interactiveSetupStart($input);
		$this->claimInteractiveSetupSession($gateway, $response);

		return new DataResponse($response);
	}

	/**
	 * Continue an interactive setup flow
	 *
	 * @param string $gateway The gateway id
	 * @param string $sessionId Interactive setup session id
	 * @param string $action Action to execute in the setup flow
	 * @param array<string, mixed> $input Step input
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{message: string}, array{}>
	 *
	 * 200: Interactive setup step executed
	 * 400: Unknown gateway, invalid provider, or interactive setup unsupported
	 * 403: Interactive setup session belongs to a different actor
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'POST', url: '/admin/gateways/{gateway}/interactive-setup/step')]
	public function interactiveSetupStep(string $gateway, string $sessionId, string $action, array $input = []): DataResponse {
		try {
			$gw = $this->resolveGatewayForPayload($gateway, $input);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		if (!($gw instanceof IInteractiveSetupGateway)) {
			return new DataResponse(['message' => 'Gateway does not support interactive setup.'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->gatewayInteractiveSetupSessionService->assertCanAccess($this->currentActor(), $gateway, $sessionId);
		} catch (GatewayPermissionDeniedException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}

		$response = $gw->interactiveSetupStep($sessionId, $action, $input);
		$this->releaseInteractiveSetupSessionIfFinished($gateway, $sessionId, $response);

		return new DataResponse($response);
	}

	/**
	 * Cancel an interactive setup flow
	 *
	 * @param string $gateway The gateway id
	 * @param string $sessionId Interactive setup session id
	 * @param array<string, string> $input Optional setup context used to resolve catalog providers
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>|DataResponse<Http::STATUS_FORBIDDEN, array{message: string}, array{}>
	 *
	 * 200: Interactive setup cancelled
	 * 400: Unknown gateway or interactive setup unsupported
	 * 403: Interactive setup session belongs to a different actor
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'POST', url: '/admin/gateways/{gateway}/interactive-setup/cancel')]
	public function cancelInteractiveSetup(string $gateway, string $sessionId, array $input = []): DataResponse {
		try {
			$gw = $this->resolveGatewayForPayload($gateway, $input);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		if (!($gw instanceof IInteractiveSetupGateway)) {
			return new DataResponse(['message' => 'Gateway does not support interactive setup.'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->gatewayInteractiveSetupSessionService->assertCanAccess($this->currentActor(), $gateway, $sessionId);
		} catch (GatewayPermissionDeniedException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}

		$response = $gw->interactiveSetupCancel($sessionId);
		$this->gatewayInteractiveSetupSessionService->release($gateway, $sessionId);

		return new DataResponse($response);
	}

	/** @param array<string, mixed> $response */
	private function claimInteractiveSetupSession(string $gateway, array $response): void {
		$sessionId = trim((string)($response['sessionId'] ?? ''));
		if ($sessionId === '') {
			return;
		}

		$this->gatewayInteractiveSetupSessionService->claim($this->currentActor(), $gateway, $sessionId);
	}

	/** @param array<string, mixed> $response */
	private function releaseInteractiveSetupSessionIfFinished(string $gateway, string $sessionId, array $response): void {
		if (!$this->isInteractiveSetupTerminalResponse($response)) {
			return;
		}

		$this->gatewayInteractiveSetupSessionService->release($gateway, $sessionId);
	}

	/** @param array<string, mixed> $response */
	private function isInteractiveSetupTerminalResponse(array $response): bool {
		$status = trim((string)($response['status'] ?? ''));
		if (in_array($status, ['done', 'cancelled'], true)) {
			return true;
		}

		if ($status === 'ok' && ((string)($response['step'] ?? '') === 'complete' || array_key_exists('result', $response))) {
			return true;
		}

		if ($status !== 'error') {
			return false;
		}

		$message = strtolower(trim((string)($response['message'] ?? '')));
		return $message !== '' && (str_contains($message, 'not found') || str_contains($message, 'expired'));
	}

	private function resolveGatewayForPayload(string $gateway, array $config): IGateway {
		$resolvedGateway = $this->gatewayFactory->get($gateway);
		if (!($resolvedGateway instanceof \OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway)) {
			if (method_exists($resolvedGateway, 'withRuntimeConfig')) {
				$runtimeGateway = $resolvedGateway->withRuntimeConfig($config);
				if ($runtimeGateway instanceof IGateway) {
					return $runtimeGateway;
				}
			}

			return $resolvedGateway;
		}

		$selectorField = $resolvedGateway->getProviderSelectorField()->field;
		$selectedProvider = (string)($config[$selectorField] ?? '');
		if ($selectedProvider === '') {
			$catalog = $resolvedGateway->getProviderCatalog();
			if (count($catalog) !== 1) {
				return $resolvedGateway;
			}

			$selectedProvider = (string)($catalog[0]['id'] ?? '');
			if ($selectedProvider === '') {
				return $resolvedGateway;
			}
		}

		$providerGateway = $this->resolveCatalogGatewayByProvider($resolvedGateway, $selectedProvider);
		if (method_exists($providerGateway, 'withRuntimeConfig')) {
			$runtimeGateway = $providerGateway->withRuntimeConfig($config);
			if ($runtimeGateway instanceof IGateway) {
				return $runtimeGateway;
			}
		}

		return $providerGateway;
	}

	private function resolveGatewayForInstance(string $gateway, string &$instanceId): IGateway {
		$resolvedGateway = $this->gatewayFactory->get($gateway);
		if (!($resolvedGateway instanceof \OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway)) {
			return $resolvedGateway;
		}

		$instanceReference = $this->parseCatalogInstanceReference($instanceId);
		if ($instanceReference === null) {
			return $resolvedGateway;
		}

		[$providerId, $innerInstanceId] = $instanceReference;
		$providerGateway = $this->resolveCatalogGatewayByProvider($resolvedGateway, $providerId);
		if ($providerGateway !== $resolvedGateway) {
			$instanceId = $innerInstanceId;
		}

		return $providerGateway;
	}

	private function resolveGatewayForUpdate(string $gateway, string &$instanceId, array $config): IGateway {
		$instanceGateway = $this->resolveGatewayForInstance($gateway, $instanceId);
		if ($instanceGateway->getProviderId() !== $gateway) {
			return $instanceGateway;
		}

		return $this->resolveGatewayForConfigurationPayload($gateway, $config);
	}

	private function resolveGatewayForConfigurationPayload(string $gateway, array $config): IGateway {
		$resolvedGateway = $this->gatewayFactory->get($gateway);
		if (method_exists($resolvedGateway, 'withRuntimeConfig')) {
			$runtimeGateway = $resolvedGateway->withRuntimeConfig($config);
			if ($runtimeGateway instanceof IGateway) {
				return $runtimeGateway;
			}
		}

		return $resolvedGateway;
	}

	private function resolveCatalogGatewayByProvider(IGateway $gateway, string $providerId): IGateway {
		if (!($gateway instanceof \OCA\TwoFactorGateway\Provider\Gateway\IProviderCatalogGateway)) {
			return $gateway;
		}

		if ($providerId === $gateway->getProviderId()) {
			return $gateway;
		}

		$providerIds = array_map(
			static fn (array $provider): string => (string)($provider['id'] ?? ''),
			$gateway->getProviderCatalog(),
		);
		if (!in_array($providerId, $providerIds, true)) {
			return $gateway;
		}

		try {
			$resolvedProvider = $this->gatewayFactory->get($providerId);
			if ($resolvedProvider instanceof IGateway) {
				return $resolvedProvider;
			}

			return $gateway;
		} catch (\InvalidArgumentException) {
			return $gateway;
		}
	}

	/** @return array{0: string, 1: string}|null */
	private function parseCatalogInstanceReference(string $instanceId): ?array {
		$separatorPosition = strpos($instanceId, ':');
		if ($separatorPosition === false || $separatorPosition === 0 || $separatorPosition >= strlen($instanceId) - 1) {
			return null;
		}

		return [
			substr($instanceId, 0, $separatorPosition),
			substr($instanceId, $separatorPosition + 1),
		];
	}

	private function currentActor(): ?IUser {
		$user = $this->userSession->getUser();
		return $user instanceof IUser ? $user : null;
	}

	/**
	 * @param array<string, string> $config
	 * @throws GatewayPermissionDeniedException
	 */
	private function assertCanWriteConfigurationFields(?IUser $actor, IGateway $gateway, array $config): void {
		$scope = $this->gatewayPermissionService->resolveViewScope($actor);
		if ($scope === GatewayViewScope::ADMIN || $config === []) {
			return;
		}

		$knownFieldNames = [];
		foreach ($gateway->getSettings()->fields as $field) {
			if ($field instanceof FieldDefinition) {
				$knownFieldNames[$field->field] = true;
			}
		}

		$writableFieldNames = [];
		foreach ($this->gatewayFieldSanitizer->filterFields($gateway->getSettings()->fields, $scope) as $field) {
			$writableFieldNames[$field->field] = true;
		}

		$disallowedFields = [];
		foreach (array_keys($config) as $fieldName) {
			if (!isset($knownFieldNames[$fieldName]) || isset($writableFieldNames[$fieldName])) {
				continue;
			}

			$disallowedFields[] = $fieldName;
		}

		if ($disallowedFields === []) {
			return;
		}

		sort($disallowedFields);
		throw new GatewayPermissionDeniedException(
			'You are not allowed to modify the following fields: ' . implode(', ', $disallowedFields),
		);
	}
}
