<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCP\IUser;
use OCP\IUserSession;

/** @psalm-import-type TwoFactorGatewayAdminScreen from \OCA\TwoFactorGateway\ResponseDefinitions */
class GatewayAdminInitialStateService {
	public function __construct(
		private GatewayAdminScreenService $gatewayAdminScreenService,
		private IUserSession $userSession,
	) {
	}

	/**
	 * Build the admin bootstrap payload that is embedded into the settings page and later
	 * consumed by src/admin.ts before the live admin API takes over for mutations.
	 *
	 * @return TwoFactorGatewayAdminScreen
	 */
	public function build(int $groupLimit = 200): array {
		return $this->gatewayAdminScreenService->build($this->currentActor(), $groupLimit);
	}

	private function currentActor(): ?IUser {
		$user = $this->userSession->getUser();
		return $user instanceof IUser ? $user : null;
	}
}
