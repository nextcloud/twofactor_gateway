<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Service\Gateway\TConfigurable;
use OCP\IAppConfig;

abstract class AProvider implements IProvider {
	use TConfigurable;
	protected IAppConfig $appConfig;

	/**
	 * @throws MessageTransmissionException
	 */
	#[\Override]
	abstract public function send(string $identifier, string $message);
}
