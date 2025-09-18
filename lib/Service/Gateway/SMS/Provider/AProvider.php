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
	public IAppConfig $appConfig;

	/**
	 * @throws MessageTransmissionException
	 */
	#[\Override]
	abstract public function send(string $identifier, string $message);

	#[\Override]
	public function setAppConfig(IAppConfig $appConfig): void {
		$this->appConfig = $appConfig;
	}

	#[\Override]
	public static function idOverride(): ?string {
		return null;
	}

	#[\Override]
	public static function getProviderId(): string {
		if (static::SCHEMA['id'] ?? null) {
			return static::SCHEMA['id'];
		}
		$id = self::getIdFromProviderFqcn(static::class);
		if ($id === null) {
			throw new \LogicException('Cannot derive gateway id from FQCN: ' . static::class);
		}
		return $id;
	}

	private static function getIdFromProviderFqcn(string $fqcn): ?string {
		$prefix = 'OCA\\TwoFactorGateway\\Service\\Gateway\\SMS\\Provider\\';
		if (strncmp($fqcn, $prefix, strlen($prefix)) !== 0) {
			return null;
		}
		$type = substr($fqcn, strlen($prefix));
		if (strpos($type, '\\') !== false) {
			return null;
		}
		return $type !== '' ? strtolower($type) : null;
	}
}
