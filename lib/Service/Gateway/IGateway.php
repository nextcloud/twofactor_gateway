<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway;

use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\IUser;

interface IGateway {

	/**
	 * Get the gateway-specific configuration
	 *
	 * @return IGatewayConfig
	 */
	public function getConfig(): IGatewayConfig;

	/**
	 * @param IUser $user
	 * @param string $identifier
	 * @param string $message
	 *
	 * @throws SmsTransmissionException
	 */
	public function send(IUser $user, string $identifier, string $message);
}
