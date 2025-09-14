<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS;

use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCP\IUser;

class Gateway implements IGateway {

	public function __construct(
		public GatewayConfig $config,
	) {
	}

	#[\Override]
	public function send(IUser $user, string $identifier, string $message, array $extra = []) {
		$this->config->getProvider()->send($identifier, $message);
	}
}
