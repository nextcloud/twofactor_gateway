<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Drivers;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\QuestionHelper;

/**
 * Driver para Meta/Facebook WhatsApp Cloud API
 * Usa a API oficial v14.0+ do Meta Graph para enviar mensagens
 */
class CloudApiDriver implements IWhatsAppDriver {
	private const API_VERSION = 'v14.0';
	private const API_BASE_URL = 'https://graph.facebook.com';

	private IClient $client;
	private ?Settings $cachedSettings = null;

	public function __construct(
		private IAppConfig $appConfig,
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
		$this->client = $this->clientService->newClient();
	}

	public function send(string $identifier, string $message, array $extra = []): void {
		$this->logger->debug("Sending WhatsApp Cloud API message to $identifier");

		try {
			$phoneNumberId = $this->getConfig('phone_number_id');
			$apiKey = $this->getConfig('api_key');
			$apiEndpoint = $this->getConfig('api_endpoint') ?? self::API_BASE_URL;

			if (!$phoneNumberId || !$apiKey) {
				throw new ConfigurationException('Missing required Cloud API configuration');
			}

			// Normaliza o número de telefone removendo caracteres especiais
			$phoneNumber = preg_replace('/\D/', '', $identifier);
			if (strlen($phoneNumber) < 10) {
				throw new MessageTransmissionException('Invalid phone number format');
			}

			$url = sprintf(
				'%s/%s/%s/messages',
				rtrim($apiEndpoint, '/'),
				self::API_VERSION,
				$phoneNumberId
			);

			$response = $this->client->post($url, [
				'headers' => [
					'Authorization' => "Bearer $apiKey",
					'Content-Type' => 'application/json',
				],
				'json' => [
					'messaging_product' => 'whatsapp',
					'recipient_type' => 'individual',
					'to' => $phoneNumber,
					'type' => 'text',
					'text' => [
						'body' => $message,
					],
				],
			]);

			$statusCode = $response->getStatusCode();
			$responseBody = json_decode((string)$response->getBody(), true);

			if ($statusCode >= 200 && $statusCode < 300) {
				$this->logger->debug('WhatsApp message sent successfully', [
					'phone' => $phoneNumber,
					'message_id' => $responseBody['messages'][0]['id'] ?? 'unknown',
				]);
			} else {
				throw new MessageTransmissionException(
					'Failed to send WhatsApp message: ' . ($responseBody['error']['message'] ?? 'Unknown error')
				);
			}
		} catch (ConnectException $e) {
			$this->logger->error('Connection error sending WhatsApp message', ['exception' => $e]);
			throw new MessageTransmissionException('Failed to connect to WhatsApp API');
		} catch (ClientException | ServerException $e) {
			$errorMsg = 'Unknown error';
			try {
				$body = json_decode((string)$e->getResponse()->getBody(), true);
				$errorMsg = $body['error']['message'] ?? $body['message'] ?? $errorMsg;
			} catch (\Exception) {
				// Use default error message
			}
			$this->logger->error('WhatsApp API error', [
				'status' => $e->getResponse()->getStatusCode() ?? 'unknown',
				'error' => $errorMsg,
			]);
			throw new MessageTransmissionException("WhatsApp API error: $errorMsg");
		} catch (RequestException $e) {
			$this->logger->error('Request error sending WhatsApp message', ['exception' => $e]);
			throw new MessageTransmissionException('Failed to send WhatsApp message');
		}
	}

	public function getSettings(): Settings {
		if ($this->cachedSettings !== null) {
			return $this->cachedSettings;
		}

		$this->cachedSettings = new Settings(
			name: 'WhatsApp Cloud API (Meta)',
			allowMarkdown: true,
			fields: [
				new FieldDefinition(
					field: 'phone_number_id',
					prompt: 'Phone Number ID from Meta Business Account:',
				),
				new FieldDefinition(
					field: 'business_account_id',
					prompt: 'Business Account ID:',
				),
				new FieldDefinition(
					field: 'api_key',
					prompt: 'API Access Token (v14.0+):',
				),
				new FieldDefinition(
					field: 'api_endpoint',
					prompt: 'API Endpoint (optional, default: https://graph.facebook.com):',
				),
			],
		);

		return $this->cachedSettings;
	}

	public function validateConfig(): void {
		$phoneNumberId = $this->getConfig('phone_number_id');
		$apiKey = $this->getConfig('api_key');
		$apiEndpoint = $this->getConfig('api_endpoint') ?? self::API_BASE_URL;

		if (!$phoneNumberId || !$apiKey) {
			throw new ConfigurationException('Missing required Cloud API configuration');
		}

		try {
			$url = sprintf(
				'%s/%s/%s',
				rtrim($apiEndpoint, '/'),
				self::API_VERSION,
				$phoneNumberId
			);

			$response = $this->client->get($url, [
				'headers' => [
					'Authorization' => "Bearer $apiKey",
				],
			]);

			if ($response->getStatusCode() !== 200) {
				throw new ConfigurationException('Failed to validate Cloud API credentials');
			}
		} catch (RequestException $e) {
			$this->logger->error('Failed to validate Cloud API config', ['exception' => $e]);
			throw new ConfigurationException('Invalid Cloud API credentials or endpoint');
		}
	}

	public function isConfigComplete(): bool {
		return (bool)($this->getConfig('phone_number_id') && $this->getConfig('api_key'));
	}

	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$helper = new QuestionHelper();
		$settings = $this->getSettings();

		try {
			foreach ($settings->fields as $field) {
				$question = new Question($field->prompt . ' ');
				$value = $helper->ask($input, $output, $question);

				if (!$value) {
					$output->writeln("<error>Field '{$field->field}' is required</error>");
					return 1;
				}

				$this->setConfig($field->field, $value);
			}

			// Valida a configuração
			$this->validateConfig();
			$output->writeln('<info>WhatsApp Cloud API configuration validated successfully!</info>');

			return 0;
		} catch (\Exception $e) {
			$output->writeln("<error>Configuration failed: {$e->getMessage()}</error>");
			return 1;
		}
	}

	public static function detectDriver(array $storedConfig): ?string {
		// Este driver é detectado quando temos api_key (indicando Cloud API)
		if (!empty($storedConfig['api_key'])) {
			return self::class;
		}
		return null;
	}

	private function getConfig(string $key): ?string {
		$value = $this->appConfig->getValueString('twofactor_gateway', "whatsapp_cloud_$key", '');
		return $value ?: null;
	}

	private function setConfig(string $key, string $value): void {
		$this->appConfig->setValueString('twofactor_gateway', "whatsapp_cloud_$key", $value);
	}
}
