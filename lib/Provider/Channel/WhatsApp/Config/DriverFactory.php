<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Config;

use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Drivers\CloudApiDriver;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Drivers\IWhatsAppDriver;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Drivers\WebSocketDriver;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Factory para detectar e instanciar o driver de WhatsApp apropriado
 */
class DriverFactory {
	private const DRIVERS = [
		CloudApiDriver::class,
		WebSocketDriver::class,
	];

	public function __construct(
		private IAppConfig $appConfig,
		private IConfig $config,
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {}

	/**
	 * Cria instância do driver apropriado baseado na configuração armazenada
	 * Retorna null se nenhuma configuração for encontrada
	 *
	 * @throws ConfigurationException se nenhum driver conseguir ser detectado/instanciado
	 */
	public function create(): ?IWhatsAppDriver {
		$storedConfig = $this->getStoredConfig();

		// Se nenhuma configuração foi fornecida, retorna null
		if (empty($storedConfig['api_key']) && empty($storedConfig['base_url'])) {
			return null;
		}

		// Tenta detectar qual driver deve ser usado
		foreach (self::DRIVERS as $driverClass) {
			if ($driverClass::detectDriver($storedConfig) !== null) {
				return $this->instantiateDriver($driverClass);
			}
		}

		// Se nenhum driver for detectado, lança exceção
		throw new ConfigurationException(
			'No WhatsApp driver configuration found. Please configure one.'
		);
	}

	/**
	 * Instancia um driver específico
	 */
	private function instantiateDriver(string $driverClass): IWhatsAppDriver {
		return match ($driverClass) {
			CloudApiDriver::class => new CloudApiDriver(
				$this->appConfig,
				$this->clientService,
				$this->logger,
			),
			WebSocketDriver::class => new WebSocketDriver(
				$this->appConfig,
				$this->config,
				$this->clientService,
				$this->logger,
			),
			default => throw new ConfigurationException("Unknown driver: $driverClass"),
		};
	}

	/**
	 * Obtém a configuração armazenada de ambos os drivers
	 *
	 * @return array<string, string|null>
	 */
	private function getStoredConfig(): array {
		return [
			// Cloud API
			'api_key' => $this->appConfig->getValueString('twofactor_gateway', 'whatsapp_cloud_api_key', ''),
			'phone_number_id' => $this->appConfig->getValueString('twofactor_gateway', 'whatsapp_cloud_phone_number_id', ''),
			'business_account_id' => $this->appConfig->getValueString('twofactor_gateway', 'whatsapp_cloud_business_account_id', ''),
			'api_endpoint' => $this->appConfig->getValueString('twofactor_gateway', 'whatsapp_cloud_api_endpoint', ''),
			// WebSocket
			'base_url' => $this->appConfig->getValueString('twofactor_gateway', 'whatsapp_base_url', ''),
		];
	}
}
