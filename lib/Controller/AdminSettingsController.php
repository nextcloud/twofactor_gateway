<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AdminRequired;
use OCP\AppFramework\Http\Attribute\Route;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class AdminSettingsController extends Controller {
	public function __construct(
		IRequest $request,
	) {
		parent::__construct('twofactor_gateway', $request);
	}

	/**
	 * Show WhatsApp Cloud API configuration page
	 */
	#[AdminRequired]
	#[Route('GET', '/admin/whatsapp-settings')]
	public function whatsappSettings(): TemplateResponse {
		return new TemplateResponse('twofactor_gateway', 'admin_whatsapp_settings', []);
	}
}
