<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Controller;

use OCA\TwoFactorGateway\Exception\GatewayInstanceNotFoundException;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Service\GatewayConfigService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class AdminGatewayController extends OCSController {
	public function __construct(
		IRequest $request,
		private GatewayConfigService $configService,
		private GatewayFactory $gatewayFactory,
	) {
		parent::__construct('twofactor_gateway', $request);
	}

	// ──────────────────────────────────────────────────────────────────────────
	//  Gateways listing
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * List all available gateways with current configuration instances.
	 *
	 * @return JSONResponse<Http::STATUS_OK, list<mixed>, array{}>
	 *
	 * 200: OK
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'GET', url: '/admin/gateways')]
	public function listGateways(): JSONResponse {
		return new JSONResponse($this->configService->getGatewayList());
	}

	// ──────────────────────────────────────────────────────────────────────────
	//  Instance CRUD
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Create a new configuration instance for a gateway.
	 *
	 * @param string $gateway  The gateway id (e.g. "sms", "telegram")
	 * @param string $label    Human-readable name for this instance
	 * @param array<string, string> $config  Field values
	 *
	 * @return JSONResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>|JSONResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 201: Created
	 * 400: Unknown gateway
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'POST', url: '/admin/gateways/{gateway}/instances')]
	public function createInstance(string $gateway, string $label, array $config = []): JSONResponse {
		try {
			$gw = $this->gatewayFactory->get($gateway);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		$instance = $this->configService->createInstance($gw, $label, $config);
		return new JSONResponse($instance, Http::STATUS_CREATED);
	}

	/**
	 * Get a single configuration instance.
	 *
	 * @param string $gateway    The gateway id
	 * @param string $instanceId The instance id
	 *
	 * @return JSONResponse<Http::STATUS_OK, array<string, mixed>, array{}>|JSONResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|JSONResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Instance not found
	 * 400: Unknown gateway
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'GET', url: '/admin/gateways/{gateway}/instances/{instanceId}')]
	public function getInstance(string $gateway, string $instanceId): JSONResponse {
		try {
			$gw = $this->gatewayFactory->get($gateway);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			return new JSONResponse($this->configService->getInstance($gw, $instanceId));
		} catch (GatewayInstanceNotFoundException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Update an existing configuration instance.
	 *
	 * @param string $gateway    The gateway id
	 * @param string $instanceId The instance id
	 * @param string $label      Updated label
	 * @param array<string, string> $config  Updated field values
	 *
	 * @return JSONResponse<Http::STATUS_OK, array<string, mixed>, array{}>|JSONResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|JSONResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Instance not found
	 * 400: Unknown gateway
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'PUT', url: '/admin/gateways/{gateway}/instances/{instanceId}')]
	public function updateInstance(string $gateway, string $instanceId, string $label, array $config = []): JSONResponse {
		try {
			$gw = $this->gatewayFactory->get($gateway);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$record = $this->configService->updateInstance($gw, $instanceId, $label, $config);
			return new JSONResponse($record);
		} catch (GatewayInstanceNotFoundException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Delete a configuration instance.
	 *
	 * @param string $gateway    The gateway id
	 * @param string $instanceId The instance id
	 *
	 * @return JSONResponse<Http::STATUS_OK, array{}, array{}>|JSONResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|JSONResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Instance not found
	 * 400: Unknown gateway
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'DELETE', url: '/admin/gateways/{gateway}/instances/{instanceId}')]
	public function deleteInstance(string $gateway, string $instanceId): JSONResponse {
		try {
			$gw = $this->gatewayFactory->get($gateway);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->configService->deleteInstance($gw, $instanceId);
			return new JSONResponse([]);
		} catch (GatewayInstanceNotFoundException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	// ──────────────────────────────────────────────────────────────────────────
	//  Set default
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Promote an instance to be the default for its gateway.
	 *
	 * The instance's field values are mirrored to the primary config keys used by
	 * the 2-FA flow and CLI commands.
	 *
	 * @param string $gateway    The gateway id
	 * @param string $instanceId The instance id to promote
	 *
	 * @return JSONResponse<Http::STATUS_OK, array{}, array{}>|JSONResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|JSONResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: OK
	 * 404: Instance not found
	 * 400: Unknown gateway
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'POST', url: '/admin/gateways/{gateway}/instances/{instanceId}/default')]
	public function setDefaultInstance(string $gateway, string $instanceId): JSONResponse {
		try {
			$gw = $this->gatewayFactory->get($gateway);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->configService->setDefaultInstance($gw, $instanceId);
			return new JSONResponse([]);
		} catch (GatewayInstanceNotFoundException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	// ──────────────────────────────────────────────────────────────────────────
	//  Test gateway
	// ──────────────────────────────────────────────────────────────────────────

	/**
	 * Send a test message using a specific configuration instance.
	 *
	 * @param string $gateway    The gateway id
	 * @param string $instanceId The instance id to test
	 * @param string $identifier The recipient identifier (e.g. phone number)
	 *
	 * @return JSONResponse<Http::STATUS_OK, array{success: bool, message: string}, array{}>|JSONResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>|JSONResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: Test sent
	 * 400: Gateway not complete or unknown gateway
	 * 404: Instance not found
	 */
	#[AuthorizedAdminSetting(\OCA\TwoFactorGateway\Settings\AdminSettings::class)]
	#[ApiRoute(verb: 'POST', url: '/admin/gateways/{gateway}/instances/{instanceId}/test')]
	public function testInstance(string $gateway, string $instanceId, string $identifier): JSONResponse {
		try {
			$gw = $this->gatewayFactory->get($gateway);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		try {
			$instance = $this->configService->getInstance($gw, $instanceId);
		} catch (GatewayInstanceNotFoundException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}

		if (!$instance['isComplete']) {
			return new JSONResponse(
				['message' => 'Gateway instance is not fully configured.'],
				Http::STATUS_BAD_REQUEST,
			);
		}

		try {
			$gw->send($identifier, 'Test');
			return new JSONResponse(['success' => true, 'message' => 'Test message sent successfully.']);
		} catch (MessageTransmissionException $e) {
			return new JSONResponse(
				['success' => false, 'message' => $e->getMessage()],
				Http::STATUS_BAD_REQUEST,
			);
		}
	}
}
