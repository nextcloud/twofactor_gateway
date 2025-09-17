<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Exception\InvalidProviderException;
use OCP\IAppConfig;
use OCP\Server;

class ProviderFactory {
	public function getProvider(string $id): IProvider {
		foreach ($this->discoverProviders() as $provider) {
			if ($provider::SCHEMA['id'] === $id) {
				$instance = Server::get($provider);
				$instance->setAppConfig(Server::get(IAppConfig::class));
				return $instance;
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
				&& defined("$namespace::SCHEMA")
				&& is_array($namespace::SCHEMA)
				&& isset($namespace::SCHEMA['id'])
		);
	}

	public function getSchemas(): array {
		$schemas = [];
		foreach ($this->discoverProviders() as $providerClass) {
			$schemas[] = $providerClass::SCHEMA;
		}
		return $schemas;
	}
}
