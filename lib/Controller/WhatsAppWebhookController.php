<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class WhatsAppWebhookController extends Controller {
	public function __construct(
		IRequest $request,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
		parent::__construct('twofactor_gateway', $request);
	}

	/**
	 * Verify webhook (Facebook sends GET request during setup)
	 *
	 * @param string $hub_mode
	 * @param string $hub_challenge
	 * @param string $hub_verify_token
	 * @return DataResponse
	 */
	#[ApiRoute(verb: 'GET', url: '/api/v1/webhooks/whatsapp')]
	#[NoAdminRequired]
	public function verify(
		string $hub_mode = '',
		string $hub_challenge = '',
		string $hub_verify_token = '',
	): DataResponse {
		try {
			// Get stored verification token
			$storedToken = $this->appConfig->getValueString('twofactor_gateway', 'whatsapp_cloud_verify_token', '');

			// Verify the mode and token
			if ($hub_mode === 'subscribe' && $hub_verify_token === $storedToken) {
				$this->logger->info('WhatsApp webhook verified successfully');
				return new DataResponse($hub_challenge, 200, [
					'Content-Type' => 'text/plain',
				]);
			}

			$this->logger->warning('Invalid webhook verification token');
			return new DataResponse(['error' => 'Invalid verification token'], 403);
		} catch (\Exception $e) {
			$this->logger->error('Error verifying webhook', ['exception' => $e]);
			return new DataResponse(['error' => 'Error verifying webhook'], 500);
		}
	}

	/**
	 * Handle incoming webhook messages (Facebook sends POST requests)
	 *
	 * @return DataResponse
	 */
	#[ApiRoute(verb: 'POST', url: '/api/v1/webhooks/whatsapp')]
	#[NoAdminRequired]
	public function webhook(): DataResponse {
		try {
			$body = $this->request->getParams();

			// Log the webhook payload
			$this->logger->debug('WhatsApp webhook received', ['payload' => $body]);

			// TODO: Process incoming messages from WhatsApp
			// This is where you would handle message status updates, incoming messages, etc.

			// Facebook requires a 200 response quickly
			return new DataResponse(['success' => true], 200);
		} catch (\Exception $e) {
			$this->logger->error('Error processing webhook', ['exception' => $e]);
			return new DataResponse(['success' => false], 500);
		}
	}
}
