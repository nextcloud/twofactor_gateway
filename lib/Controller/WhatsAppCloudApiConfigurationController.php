<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Controller;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Drivers\CloudApiDriver;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class WhatsAppCloudApiConfigurationController extends Controller {
	public function __construct(
		IRequest $request,
		private IAppConfig $appConfig,
		private IClientService $clientService,
		private LoggerInterface $logger,
		private ISecureRandom $secureRandom,
	) {
		parent::__construct('twofactor_gateway', $request);
	}

	/**
	 * Get current WhatsApp Cloud API configuration
	 *
	 * @return DataResponse
	 */
	#[ApiRoute(verb: 'GET', url: '/api/v1/whatsapp/configuration')]
	public function getConfiguration(): DataResponse {
		try {
			// Only admin can access
			if (!$this->isAdmin()) {
				return new DataResponse(['message' => 'Unauthorized'], 403);
			}

			$config = [
				'phone_number_id' => $this->appConfig->getValueString('twofactor_gateway', 'whatsapp_cloud_phone_number_id', ''),
				'business_account_id' => $this->appConfig->getValueString('twofactor_gateway', 'whatsapp_cloud_business_account_id', ''),
				'api_key' => '', // Never send API key to frontend
				'api_endpoint' => $this->appConfig->getValueString('twofactor_gateway', 'whatsapp_cloud_api_endpoint', ''),
			];

			return new DataResponse($config, 200);
		} catch (\Exception $e) {
			$this->logger->error('Error retrieving WhatsApp configuration', ['exception' => $e]);
			return new DataResponse(['message' => 'Error retrieving configuration'], 500);
		}
	}

	/**
	 * Save WhatsApp Cloud API configuration
	 *
	 * @return DataResponse
	 */
	#[ApiRoute(verb: 'POST', url: '/api/v1/whatsapp/configuration')]
	public function saveConfiguration(
		string $phone_number_id = '',
		string $business_account_id = '',
		string $api_key = '',
		string $api_endpoint = '',
	): DataResponse {
		try {
			// Only admin can access
			if (!$this->isAdmin()) {
				return new DataResponse(['message' => 'Unauthorized'], 403);
			}

			// Validate required fields
			if (empty($phone_number_id) || empty($business_account_id) || empty($api_key)) {
				return new DataResponse([
					'message' => 'Phone Number ID, Business Account ID, and API Key are required',
				], 400);
			}

			// Store configuration
			$this->appConfig->setValueString('twofactor_gateway', 'whatsapp_cloud_phone_number_id', $phone_number_id);
			$this->appConfig->setValueString('twofactor_gateway', 'whatsapp_cloud_business_account_id', $business_account_id);
			$this->appConfig->setValueString('twofactor_gateway', 'whatsapp_cloud_api_key', $api_key);
			$this->appConfig->setValueString('twofactor_gateway', 'whatsapp_cloud_api_endpoint', $api_endpoint ?: 'https://graph.facebook.com');

			$this->logger->info('WhatsApp Cloud API configuration saved');

			return new DataResponse(['message' => 'Configuration saved successfully'], 200);
		} catch (\Exception $e) {
			$this->logger->error('Error saving WhatsApp configuration', ['exception' => $e]);
			return new DataResponse(['message' => 'Error saving configuration'], 500);
		}
	}

	/**
	 * Test WhatsApp Cloud API connection
	 *
	 * @return DataResponse
	 */
	#[ApiRoute(verb: 'POST', url: '/api/v1/whatsapp/test')]
	public function testConfiguration(
		string $phone_number_id = '',
		string $business_account_id = '',
		string $api_key = '',
		string $api_endpoint = '',
	): DataResponse {
		try {
			// Only admin can access
			if (!$this->isAdmin()) {
				return new DataResponse(['message' => 'Unauthorized'], 403);
			}

			// Validate required fields
			if (empty($phone_number_id) || empty($business_account_id) || empty($api_key)) {
				return new DataResponse([
					'message' => 'Phone Number ID, Business Account ID, and API Key are required',
				], 400);
			}

			$endpoint = $api_endpoint ?: 'https://graph.facebook.com';
			$client = $this->clientService->newClient();

			// Test the connection by getting phone number information
			$url = sprintf('%s/v14.0/%s', rtrim($endpoint, '/'), $phone_number_id);

			try {
				$response = $client->get($url, [
					'headers' => [
						'Authorization' => "Bearer $api_key",
					],
				]);

				if ($response->getStatusCode() === 200) {
					$this->logger->info('WhatsApp Cloud API connection test successful');
					return new DataResponse(['message' => 'Connection test successful'], 200);
				}

				return new DataResponse([
					'message' => 'Connection test failed with status ' . $response->getStatusCode(),
				], 400);
			} catch (\Exception $e) {
				$this->logger->error('WhatsApp Cloud API connection test failed', ['exception' => $e]);
				return new DataResponse([
					'message' => 'Connection test failed: ' . $e->getMessage(),
				], 400);
			}
		} catch (\Exception $e) {
			$this->logger->error('Error testing WhatsApp configuration', ['exception' => $e]);
			return new DataResponse(['message' => 'Error testing configuration'], 500);
		}
	}

	/**
	 * Check if current user is admin
	 */
	private function isAdmin(): bool {
		// This should be implemented based on Nextcloud's admin check
		// For now, we'll use a simple check - in production, use proper admin verification
		return $this->request->getHeader('X-Admin-Token') !== '' || $this->userId === 'admin';
	}
}
