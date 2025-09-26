<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Gateway\TConfigurable;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\IAppConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AProvider implements IProvider {
	use TConfigurable;
	public IAppConfig $appConfig;
	protected ?Settings $settings = null;

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
	public function getSettings(): Settings {
		if ($this->settings !== null) {
			return $this->settings;
		}
		return $this->settings = $this->createSettings();
	}

	#[\Override]
	public static function idOverride(): ?string {
		return null;
	}

	#[\Override]
	public function getProviderId(): string {
		if (!empty($this->settings->id)) {
			return $this->settings->id;
		}
		$id = self::getIdFromProviderFqcn(static::class);
		if ($id === null) {
			throw new \LogicException('Cannot derive gateway id from FQCN: ' . static::class);
		}
		return $id;
	}

	#[\Override]
	abstract public function cliConfigure(InputInterface $input, OutputInterface $output): int;

	private static function getIdFromProviderFqcn(string $fqcn): ?string {
		$prefix = 'OCA\\TwoFactorGateway\\Provider\\Channel\\Telegram\\Provider\\Drivers\\';
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
