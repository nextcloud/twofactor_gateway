<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Controller;

use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Exception\GatewayInstanceNotFoundException;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IInteractiveSetupGateway;
use OCA\TwoFactorGateway\Provider\Gateway\ITestResultEnricher;
use OCA\TwoFactorGateway\Service\GatewayConfigService;
use OCA\TwoFactorGateway\Service\GatewayConfigurationSyncService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IGroupManager;
use OCP\IRequest;

class AdminGatewayController extends OCSController {
	public function __construct(
		IRequest $request,
		private GatewayConfigService $configService,
		private GatewayFactory $gatewayFactory,
		private GatewayConfigurationSyncService $gatewayConfigurationSyncService,
		private IGroupManager $groupManager,
	) {
		parent::__construct('twofactor_gateway', $request);
	}

	/**
	 * List all available gateways with current configuration instances.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: OK
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'GET', url: '/admin/gateways')]
	public function listGateways(): DataResponse {
		return new DataResponse($this->configService->getGatewayList());
	}

	/**
	 * List assignable Nextcloud groups for per-instance routing.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array{id: string, displayName: string}>, array{}>
	 *
	 * 200: OK
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'GET', url: '/admin/groups')]
	public function getGroups(): DataResponse {
		$groups = array_map(
			static fn ($group): array => [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			],
			$this->groupManager->search(''),
		);

		usort($groups, static fn (array $left, array $right): int => strcasecmp($left['displayName'], $right['displayName']));

		return new DataResponse($groups);
	}

	/**
	 * Create a new configuration instance for a gateway.
	 *
	 * @param string $gateway The gateway id (e.g. "sms", "telegram")
	 * @param string $label Human-readable name for this instance
	 * @param array<string, string> $config Field values
	 * @param list<string> $groupIds Optional group restrictions for routing
	 * @param int $priority Optional routing priority (higher runs first)
	 *
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 201: Created
	 * 400: Unknown gateway
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'POST', url: '/admin/gateways/{gateway}/instances')]
	public function createInstance(string $gateway, string $label, array $config = [], array $groupIds = [], int $priority = 0): DataResponse {
		try {
			$gw = $this->resolveGatewayForPayload($gateway, $config);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		$instance = $this->configService->createInstance($gw, $label, $config, $groupIds, $priority);
		$this->syncSessionMonitorSafely($gw);
		return new DataResponse($instance, Http::STATUS_CREATED);
	}

	/**
	 * Get a single configuration instance.
	 *
	 * @param string $gateway The gateway id
	 * @param string $instanceId The instance id
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Instance not found
	 * 400: Unknown gateway
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'GET', url: '/admin/gateways/{gateway}/instances/{instanceId}')]
	public function getInstance(string $gateway, string $instanceId): DataResponse {
		try {
			$gw = $this->resolveGatewayForInstance($gateway, $instanceId);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			return new DataResponse($this->configService->getInstance($gw, $instanceId));
		} catch (GatewayInstanceNotFoundException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Update an existing configuration instance.
	 *
	 * @param string $gateway The gateway id
	 * @param string $instanceId The instance id
	 * @param string $label Updated label
	 * @param array<string, string> $config Updated field values
	 * @param list<string> $groupIds Updated group restrictions for routing
	 * @param int $priority Updated routing priority
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Instance not found
	 * 400: Unknown gateway
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'PUT', url: '/admin/gateways/{gateway}/instances/{instanceId}')]
	public function updateInstance(string $gateway, string $instanceId, string $label, array $config = [], array $groupIds = [], int $priority = 0): DataResponse {
		try {
			$gw = $this->resolveGatewayForUpdate($gateway, $instanceId, $config);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$record = $this->configService->updateInstance($gw, $instanceId, $label, $config, $groupIds, $priority);
			$this->syncSessionMonitorSafely($gw);
			return new DataResponse($record);
		} catch (GatewayInstanceNotFoundException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Delete a configuration instance.
	 *
	 * @param string $gateway The gateway id
	 * @param string $instanceId The instance id
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Instance not found
	 * 400: Unknown gateway
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'DELETE', url: '/admin/gateways/{gateway}/instances/{instanceId}')]
	public function deleteInstance(string $gateway, string $instanceId): DataResponse {
		try {
			$gw = $this->resolveGatewayForInstance($gateway, $instanceId);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->configService->deleteInstance($gw, $instanceId);
			$this->syncSessionMonitorSafely($gw);
			return new DataResponse([]);
		} catch (GatewayInstanceNotFoundException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Promote an instance to be the default for its gateway.
	 *
	 * The instance's field values are mirrored to the primary config keys used by
	 * the 2-FA flow and CLI commands.
	 *
	 * @param string $gateway The gateway id
	 * @param string $instanceId The instance id to promote
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Instance not found
	 * 400: Unknown gateway
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'POST', url: '/admin/gateways/{gateway}/instances/{instanceId}/default')]
	public function setDefaultInstance(string $gateway, string $instanceId): DataResponse {
		try {
			$gw = $this->resolveGatewayForInstance($gateway, $instanceId);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->configService->setDefaultInstance($gw, $instanceId);
			$this->syncSessionMonitorSafely($gw);
			return new DataResponse([]);
		} catch (GatewayInstanceNotFoundException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
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
	 * Send a test message using a specific configuration instance.
	 *
	 * @param string $gateway The gateway id
	 * @param string $instanceId The instance id to test
	 * @param string $identifier The recipient identifier (e.g. phone number)
	 *
	 * @return DataResponse<Http::STATUS_OK, array{success: bool, message: string, accountInfo?: array<string, string>}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: Test sent
	 * 400: Gateway not complete or unknown gateway
	 * 404: Instance not found
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'POST', url: '/admin/gateways/{gateway}/instances/{instanceId}/test')]
	public function testInstance(string $gateway, string $instanceId, string $identifier): DataResponse {
		$identifier = $this->normalizeTestIdentifier($gateway, trim($identifier));

		try {
			$gw = $this->resolveGatewayForInstance($gateway, $instanceId);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$instance = $this->configService->getInstance($gw, $instanceId);
		} catch (GatewayInstanceNotFoundException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}

		if (!$instance['isComplete']) {
			return new DataResponse(
				['message' => 'Gateway instance is not fully configured.'],
				Http::STATUS_BAD_REQUEST,
			);
		}

		$gatewayForTest = $gw;
		$instanceConfig = is_array($instance['config'] ?? null) ? $instance['config'] : [];
		if ($instanceConfig !== [] && method_exists($gw, 'withRuntimeConfig')) {
			$runtimeGateway = $gw->withRuntimeConfig($instanceConfig);
			if ($runtimeGateway instanceof IGateway) {
				$gatewayForTest = $runtimeGateway;
			}
		}

		try {
			$gatewayForTest->send($identifier, 'Test');
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
			return new DataResponse(
				['success' => false, 'message' => $e->getMessage()],
				Http::STATUS_BAD_REQUEST,
			);
		} catch (\Throwable) {
			return new DataResponse(
				['success' => false, 'message' => 'Gateway test failed unexpectedly.'],
				Http::STATUS_BAD_REQUEST,
			);
		}
	}

	private function normalizeTestIdentifier(string $gateway, string $identifier): string {
		if ($gateway !== 'telegram') {
			return $identifier;
		}

		if ($identifier === '' || str_starts_with($identifier, '@') || str_starts_with($identifier, '+')) {
			return $identifier;
		}

		if (preg_match('/^-?\d+$/', $identifier) === 1) {
			return $identifier;
		}

		if (preg_match('/^[A-Za-z][A-Za-z0-9_]{2,}$/', $identifier) === 1) {
			return '@' . $identifier;
		}

		return $identifier;
	}

	/**
	 * Start an interactive setup flow for gateways that support it.
	 *
	 * @param string $gateway The gateway id
	 * @param array<string, string> $input Initial setup input
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: Interactive setup started
	 * 400: Unknown gateway, invalid provider, or interactive setup unsupported
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

		return new DataResponse($gw->interactiveSetupStart($input));
	}

	/**
	 * Continue an interactive setup flow.
	 *
	 * @param string $gateway The gateway id
	 * @param string $sessionId Interactive setup session id
	 * @param string $action Action to execute in the setup flow
	 * @param array<string, mixed> $input Step input
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: Interactive setup step executed
	 * 400: Unknown gateway, invalid provider, or interactive setup unsupported
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

		return new DataResponse($gw->interactiveSetupStep($sessionId, $action, $input));
	}

	/**
	 * Cancel an interactive setup flow.
	 *
	 * @param string $gateway The gateway id
	 * @param string $sessionId Interactive setup session id
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: Interactive setup cancelled
	 * 400: Unknown gateway or interactive setup unsupported
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'POST', url: '/admin/gateways/{gateway}/interactive-setup/cancel')]
	public function cancelInteractiveSetup(string $gateway, string $sessionId): DataResponse {
		try {
			$gw = $this->gatewayFactory->get($gateway);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		if (!($gw instanceof IInteractiveSetupGateway)) {
			return new DataResponse(['message' => 'Gateway does not support interactive setup.'], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($gw->interactiveSetupCancel($sessionId));
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

		return $this->resolveGatewayForPayload($gateway, $config);
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
}
