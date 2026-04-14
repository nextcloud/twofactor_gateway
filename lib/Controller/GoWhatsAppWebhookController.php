<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Controller;

use OCA\TwoFactorGateway\Provider\Channel\GoWhatsApp\WebhookIngestionService;
use OCP\AppFramework\Controller;
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
	 * Ingests signed GoWhatsApp webhook events for hybrid monitoring.
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

		return new JSONResponse([
			'processed' => $result['processed'],
			'message' => $result['message'],
		], $result['status']);
	}
}
