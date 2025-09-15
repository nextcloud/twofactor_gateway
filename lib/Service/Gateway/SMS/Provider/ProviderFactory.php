<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Exception\InvalidProviderException;
use Psr\Container\ContainerInterface;

class ProviderFactory {

	public function __construct(
		private ContainerInterface $container,
	) {
	}

	public function getProvider(string $id): IProvider {
		foreach ($this->discoverProviders() as $provider) {
			if ($provider::SMS_SCHEMA['id'] === $id) {
				return $this->container->get(str_replace('Config', '', $provider));
			}
		}
		throw new InvalidProviderException("Provider <$id> does not exist");
	}

	private function discoverProviders(): array {
		$loader = require __DIR__ . '/../../../../../vendor/autoload.php';
		$classMap = $loader->getClassMap();

		return array_filter(
			array_keys($classMap),
			fn ($namespace): bool
				=> str_starts_with($namespace, 'OCA\\TwoFactorGateway\\Service\\Gateway\\SMS\\Provider\\')
				&& str_ends_with($namespace, 'Config')
				&& is_array($namespace::SMS_SCHEMA)
				&& isset($namespace::SMS_SCHEMA['id'])
		);
	}

	public function getSchemas(): array {
		$schemas = [];
		foreach ($this->discoverProviders() as $providerClass) {
			$schemas[] = $providerClass::SMS_SCHEMA;
		}
		return $schemas;
	}
}
