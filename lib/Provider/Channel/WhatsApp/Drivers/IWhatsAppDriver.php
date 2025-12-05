<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Drivers;

use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Settings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface para diferentes drivers de WhatsApp
 * Define contrato que cada estratégia de envio deve implementar
 */
interface IWhatsAppDriver {
	/**
	 * Envia uma mensagem via WhatsApp
	 *
	 * @param string $identifier Identificador do destinatário (número de telefone)
	 * @param string $message Mensagem a enviar
	 * @param array<string, mixed> $extra Dados adicionais (código, etc)
	 * @throws MessageTransmissionException
	 * @throws ConfigurationException
	 */
	public function send(string $identifier, string $message, array $extra = []): void;

	/**
	 * Obtém as configurações necessárias para este driver
	 */
	public function getSettings(): Settings;

	/**
	 * Valida se a configuração atual é válida
	 *
	 * @throws ConfigurationException
	 */
	public function validateConfig(): void;

	/**
	 * Verifica se a configuração está completa
	 */
	public function isConfigComplete(): bool;

	/**
	 * Processa a configuração via CLI
	 */
	public function cliConfigure(InputInterface $input, OutputInterface $output): int;

	/**
	 * Detecta qual driver deve ser usado baseado na configuração armazenada
	 * Retorna o nome da classe do driver ou null se não for este driver
	 */
	public static function detectDriver(array $storedConfig): ?string;
}
