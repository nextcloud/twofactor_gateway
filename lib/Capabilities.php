<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway;

use OCP\App\IAppManager;
use OCP\Capabilities\IPublicCapability;
use Override;

/**
 * @psalm-import-type TwoFactorGatewayCapabilities from ResponseDefinitions
 */
final class Capabilities implements IPublicCapability {
	public const FEATURES = [
	];

	public function __construct(
		protected IAppManager $appManager,
	) {
	}

	/**
	 * @return array{
	 *      twofactorgateway?: TwoFactorGatewayCapabilities,
	 * }
	 */
	#[Override]
	public function getCapabilities(): array {
		$capabilities = [
			'features' => self::FEATURES,
			'config' => [
			],
			'version' => $this->appManager->getAppVersion('twofactorgateway'),
		];

		return [
			'twofactorgateway' => $capabilities,
		];
	}
}
