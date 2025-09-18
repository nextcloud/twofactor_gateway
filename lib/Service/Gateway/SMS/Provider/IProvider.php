<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCP\IAppConfig;

interface IProvider {
	public const SCHEMA = [];

	/**
	 * @param string $identifier
	 * @param string $message
	 *
	 * @throws MessageTransmissionException
	 */
	public function send(string $identifier, string $message);

	public static function idOverride(): ?string;

	public static function getProviderId(): string;

	public function setAppConfig(IAppConfig $appConfig): void;
}
