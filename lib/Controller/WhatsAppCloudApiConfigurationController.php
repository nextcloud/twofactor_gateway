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
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;
use OCP\Util;
use Psr\Log\LoggerInterface;

class WhatsAppCloudApiConfigurationController extends Controller {
	public function __construct(
		IRequest $request,
		private IAppConfig $appConfig,
		private IClientService $clientService,
		private LoggerInterface $logger,
		private ISecureRandom $secureRandom,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
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
				'phone_number_fb' => $this->appConfig->getValueString('twofactor_gateway', 'whatsapp_cloud_phone_number_fb', ''),
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
		string $phone_number_fb = '',
		string $business_account_id = '',
		string $api_key = '',
		string $api_endpoint = '',
	): DataResponse {
		try {
			// Only admin can access
			if (!$this->isAdmin()) {
				return new DataResponse(['message' => 'Unauthorized'], 403);
			}

		// Validate required fields (api_key is optional - only save if provided)
		if (empty($phone_number_id) || empty($business_account_id)) {
			return new DataResponse([
				'message' => 'Phone Number ID and Business Account ID are required',
			], 400);
		}

		// Store configuration
		$this->appConfig->setValueString('twofactor_gateway', 'whatsapp_cloud_phone_number_id', $phone_number_id);
		$this->appConfig->setValueString('twofactor_gateway', 'whatsapp_cloud_phone_number_fb', $phone_number_fb);
		$this->appConfig->setValueString('twofactor_gateway', 'whatsapp_cloud_business_account_id', $business_account_id);
		if (!empty($api_key)) {
			$this->appConfig->setValueString('twofactor_gateway', 'whatsapp_cloud_api_key', $api_key);
		}
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
		string $phone_number_fb = '',
		string $business_account_id = '',
		string $api_key = '',
		string $api_endpoint = '',
	): DataResponse {
		try {
			// Only admin can access
			if (!$this->isAdmin()) {
				return new DataResponse(['message' => 'Unauthorized'], 403);
			}

		// Validate required fields (api_key is optional - use stored key if not provided)
		if (empty($phone_number_id) || empty($business_account_id)) {
			return new DataResponse([
				'message' => 'Phone Number ID and Business Account ID are required',
			], 400);
		}

		// Use provided api_key or fall back to stored key
		if (empty($api_key)) {
			$api_key = $this->appConfig->getValueString('twofactor_gateway', 'whatsapp_cloud_api_key', '');
		}

		if (empty($api_key)) {
			return new DataResponse([
				'message' => 'API Key is required',
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
	 * Get WhatsApp webhook credentials
	 *
	 * @return DataResponse
	 */
	#[ApiRoute(verb: 'GET', url: '/api/v1/whatsapp/webhook-credentials')]
	public function getWebhookCredentials(): DataResponse {
		try {
			// Only admin can access
			if (!$this->isAdmin()) {
				return new DataResponse(['message' => 'Unauthorized'], 403);
			}

			// Generate verification token if not exists
			$token = $this->appConfig->getValueString('twofactor_gateway', 'whatsapp_cloud_verify_token', '');
			if (empty($token)) {
				$token = $this->secureRandom->generate(32, 'abcdefghijklmnopqrstuvwxyz0123456789');
				$this->appConfig->setValueString('twofactor_gateway', 'whatsapp_cloud_verify_token', $token);
			}

			// Build webhook URL
			$baseUrl = \OC::$server->getConfig()->getSystemValue('overwrite.cli.url') ?: \OC::$server->getConfig()->getSystemValue('url');
			$webhookUrl = rtrim($baseUrl, '/') . '/ocs/v2.php/apps/twofactor_gateway/api/v1/webhooks/whatsapp';

			return new DataResponse([
				'webhook_url' => $webhookUrl,
				'verify_token' => $token,
			], 200);
		} catch (\Exception $e) {
			$this->logger->error('Error retrieving WhatsApp webhook credentials', ['exception' => $e]);
			return new DataResponse(['message' => 'Error retrieving webhook credentials'], 500);
		}
	}

	/**
	 * Check if current user is admin
	 */
	private function isAdmin(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}
		return $this->groupManager->isAdmin($user->getUID());
	}
}
