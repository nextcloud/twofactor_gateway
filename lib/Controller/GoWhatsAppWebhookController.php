<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Controller;

use OCA\TwoFactorGateway\Provider\Channel\GoWhatsApp\WebhookIngestionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class GoWhatsAppWebhookController extends Controller {
	public function __construct(
		IRequest $request,
		private WebhookIngestionService $webhookIngestionService,
	) {
		parent::__construct('twofactor_gateway', $request);
	}

	/**
	 * Ingest a signed GoWhatsApp webhook event for hybrid monitoring
	 *
	 * @return JSONResponse<Http::STATUS_ACCEPTED, array{processed: bool, message: string}, array{}>|JSONResponse<Http::STATUS_BAD_REQUEST, array{processed: bool, message: string}, array{}>|JSONResponse<Http::STATUS_UNAUTHORIZED, array{processed: bool, message: string}, array{}>|JSONResponse<Http::STATUS_SERVICE_UNAVAILABLE, array{processed: bool, message: string}, array{}>
	 *
	 * 202: Event accepted (processed or intentionally skipped)
	 * 400: Malformed request body
	 * 401: Invalid or missing HMAC signature
	 * 503: Webhook ingestion is disabled
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'POST', url: '/gowhatsapp/webhook')]
	public function ingest(): JSONResponse {
		$rawBody = file_get_contents('php://input');
		if ($rawBody === false) {
			$rawBody = '';
		}

		$signatureHeader = $this->request->getHeader('X-Hub-Signature-256');
		$result = $this->webhookIngestionService->ingest($rawBody, $signatureHeader);

		$status = match ($result['status']) {
			Http::STATUS_BAD_REQUEST => Http::STATUS_BAD_REQUEST,
			Http::STATUS_UNAUTHORIZED => Http::STATUS_UNAUTHORIZED,
			Http::STATUS_SERVICE_UNAVAILABLE => Http::STATUS_SERVICE_UNAVAILABLE,
			default => Http::STATUS_ACCEPTED,
		};

		return new JSONResponse([
			'processed' => $result['processed'],
			'message' => $result['message'],
		], $status);
	}
}
