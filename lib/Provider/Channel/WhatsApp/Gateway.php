<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp;

use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Config\DriverFactory;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Drivers\CloudApiDriver;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Drivers\IWhatsAppDriver;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Gateway refatorado que delega para drivers específicos via Factory pattern
 * Suporta múltiplos drivers: CloudApiDriver e WebSocketDriver
 */
class Gateway extends AGateway {
	private ?IWhatsAppDriver $driver = null;
	private DriverFactory $factory;

	public function __construct(
		public IAppConfig $appConfig,
		private IConfig $config,
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appConfig);
		$this->factory = new DriverFactory(
			$appConfig,
			$config,
			$clientService,
			$logger,
		);
	}

	#[\Override]
	public function createSettings(): Settings {
		// Retorna as configurações do driver ativo ou configurações vazias se não houver driver
		$driver = $this->factory->create();
		if ($driver === null) {
			// Retorna Settings vazio se nenhum driver for configurado
			return new Settings(
				name: 'WhatsApp Cloud API',
				id: 'whatsapp',
				instructions: 'Send two-factor authentication codes via WhatsApp',
				fields: [],
			);
		}
		return $driver->getSettings();
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		// Delega o envio para o driver
		$driver = $this->factory->create();
		if ($driver === null) {
			throw new ConfigurationException('WhatsApp is not configured');
		}
		$driver->send($identifier, $message, $extra);
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		// Se nenhum driver está configurado, use o CloudApiDriver como padrão
		$driver = $this->factory->create();
		if ($driver === null) {
			// Cria um CloudApiDriver para configuração inicial
			$driver = new CloudApiDriver($this->appConfig, $this->clientService, $this->logger);
		}
		return $driver->cliConfigure($input, $output);
	}

	/**
	 * Obtém a instância do driver, inicializando se necessário
	 *
	 * @throws ConfigurationException se nenhum driver conseguir ser criado
	 */
	private function getDriver(): IWhatsAppDriver {
		if ($this->driver === null) {
			try {
				$created = $this->factory->create();
				if ($created === null) {
					throw new ConfigurationException('No WhatsApp driver configuration found');
				}
				$this->driver = $created;
			} catch (ConfigurationException $e) {
				$this->logger->error('Failed to create WhatsApp driver', ['exception' => $e]);
				throw $e;
			}
		}

		return $this->driver;
	}
}
