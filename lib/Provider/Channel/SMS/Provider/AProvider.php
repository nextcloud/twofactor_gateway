<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS\Provider;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\AbstractChannelAProvider;

abstract class AProvider extends AbstractChannelAProvider implements IProvider {

	#[\Override]
	protected static function getDriverNamespacePrefix(): string {
		return 'OCA\\TwoFactorGateway\\Provider\\Channel\\SMS\\Provider\\Drivers\\';
	}

	/**
	 * @throws MessageTransmissionException
	 */
	#[\Override]
	abstract public function send(string $identifier, string $message);
}
