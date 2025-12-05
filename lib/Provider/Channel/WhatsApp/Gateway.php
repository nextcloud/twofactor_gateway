<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp;

use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Config\DriverFactory;
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
		// Retorna as configurações do driver ativo
		return $this->getDriver()->getSettings();
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		// Delega o envio para o driver
		$this->getDriver()->send($identifier, $message, $extra);
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		// Delega a configuração para o driver
		return $this->getDriver()->cliConfigure($input, $output);
	}

	/**
	 * Obtém a instância do driver, inicializando se necessário
	 *
	 * @throws ConfigurationException se nenhum driver conseguir ser criado
	 */
	private function getDriver(): IWhatsAppDriver {
		if ($this->driver === null) {
			try {
				$this->driver = $this->factory->create();
			} catch (ConfigurationException $e) {
				$this->logger->error('Failed to create WhatsApp driver', ['exception' => $e]);
				throw $e;
			}
		}

		return $this->driver;
	}
}
