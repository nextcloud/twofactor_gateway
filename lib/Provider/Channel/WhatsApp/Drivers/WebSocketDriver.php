<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Drivers;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Vendor\BaconQrCode\Renderer\PlainTextRenderer;
use OCA\TwoFactorGateway\Vendor\BaconQrCode\Writer;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Driver WebSocket para WhatsApp via WhatsApp Web
 * Mantém compatibilidade com configurações existentes
 */
class WebSocketDriver implements IWhatsAppDriver {
	private string $instanceId;
	private IClient $client;
	private string $lazyBaseUrl = '';

	public function __construct(
		private IAppConfig $appConfig,
		private IConfig $config,
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
		$this->instanceId = $this->config->getSystemValue('instanceid');
		$this->client = $this->clientService->newClient();
	}

	public function send(string $identifier, string $message, array $extra = []): void {
		$this->logger->debug("sending whatsapp message to $identifier, message: $message");

		$response = $this->getSessionStatus();
		if ($response !== 'CONNECTED') {
			throw new MessageTransmissionException('WhatsApp session is not connected. Current status: ' . $response);
		}

		$chatId = $this->getChatIdFromPhoneNumber($identifier);

		try {
			$this->client->post($this->getBaseUrl() . '/client/sendMessage/' . $this->instanceId, [
				'json' => [
					'chatId' => $chatId,
					'contentType' => 'string',
					'content' => $message,
					'options' => [],
				],
			]);
		} catch (\Exception $e) {
			$this->logger->error('Could not send WhatsApp message', [
				'identifier' => $identifier,
				'exception' => $e,
			]);
			throw new MessageTransmissionException();
		}

		$this->logger->debug("whatsapp message to chat $identifier sent");
	}

	public function getSettings(): Settings {
		return new Settings(
			name: 'WhatsApp (WebSocket)',
			allowMarkdown: true,
			fields: [
				new FieldDefinition(
					field: 'base_url',
					prompt: 'Base URL to your WhatsApp API endpoint:',
				),
			],
		);
	}

	public function validateConfig(): void {
		try {
			$status = $this->getSessionStatus();
			if ($status === 'not_connected') {
				throw new ConfigurationException('WhatsApp WebSocket session is not connected');
			}
		} catch (\Exception $e) {
			throw new ConfigurationException('Failed to validate WebSocket configuration: ' . $e->getMessage());
		}
	}

	public function isConfigComplete(): bool {
		return (bool)$this->getBaseUrl();
	}

	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$helper = new QuestionHelper();
		$baseUrlQuestion = new Question($this->getSettings()->fields[0]->prompt . ' ');
		$this->lazyBaseUrl = $helper->ask($input, $output, $baseUrlQuestion);
		$this->lazyBaseUrl = rtrim($this->lazyBaseUrl, '/');

		try {
			if ($this->getSessionQr($output) === 1) {
				return 1;
			}
		} catch (\Exception $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
		}

		$this->setBaseUrl($this->lazyBaseUrl);

		return 0;
	}

	public static function detectDriver(array $storedConfig): ?string {
		// Este driver é detectado quando temos base_url (indicando WebSocket)
		if (!empty($storedConfig['base_url'])) {
			return self::class;
		}
		return null;
	}

	/**
	 * @throws ConfigurationException
	 */
	public function getBaseUrl(): string {
		if ($this->lazyBaseUrl !== '') {
			return $this->lazyBaseUrl;
		}

		$this->lazyBaseUrl = $this->appConfig->getValueString('twofactor_gateway', 'whatsapp_base_url', '');
		if ($this->lazyBaseUrl === '') {
			throw new ConfigurationException('WhatsApp base URL not configured');
		}

		return $this->lazyBaseUrl;
	}

	private function setBaseUrl(string $baseUrl): void {
		$this->appConfig->setValueString('twofactor_gateway', 'whatsapp_base_url', $baseUrl);
	}

	private function getChatIdFromPhoneNumber(string $phoneNumber): string {
		try {
			$response = $this->client->post($this->getBaseUrl() . '/client/getNumberId/' . $this->instanceId, [
				'json' => [
					'number' => preg_replace('/\D/', '', $phoneNumber),
				],
			]);
			$json = $response->getBody();
			$data = json_decode($json, true);
			if (empty($data['result'])) {
				throw new MessageTransmissionException('The phone number is not registered on WhatsApp.');
			}
			return $data['result']['_serialized'];
		} catch (ServerException $e) {
			$content = $e->getResponse()?->getBody()?->getContents();
			if ($content === null) {
				throw new MessageTransmissionException('Unknown error');
			}
			$errorMessage = json_decode($content, true)['error'] ?? 'Unknown error';
			throw new MessageTransmissionException($errorMessage);
		}
	}

	private function getSessionQr(OutputInterface $output): int {
		$renderer = new PlainTextRenderer(margin: 3);
		$writer = new Writer($renderer);
		$cursor = new Cursor($output);

		if ($this->startSession() === 2) {
			$output->writeln('<info>Session already connected, no need to scan QR code.</info>');
			return 0;
		}

		$last = null;
		while (true) {
			$response = $this->client->get($this->getBaseUrl() . '/session/qr/' . $this->instanceId);
			$json = $response->getBody();
			$data = json_decode($json, true);
			if ($data['success'] === false) {
				if ($data['message'] === 'qr code not ready or already scanned') {
					$output->writeln('<error>Session not connected yet, waiting...</error>');
					sleep(2);
					continue;
				}
				$output->writeln('<error>' . $data['message'] . '</error>');
				return 1;
			}
			$qrCodeContent = $data['qr'];

			if ($qrCodeContent !== $last) {
				$last = $qrCodeContent;
				$cursor->clearScreen();
				$cursor->moveToPosition(1, 1);

				$output->write($writer->writeString($qrCodeContent));
				$output->writeln('');
				$output->writeln('<info>Please confirm on your phone.</info>');
				$output->writeln('Press Ctrl+C to exit');
			}

			sleep(1);
			if ($this->startSession() === 2) {
				return 0;
			}
		}
		return 0;
	}

	private function getSessionStatus(): string {
		$endpoint = $this->getBaseUrl() . '/session/status/' . $this->instanceId;

		try {
			$response = $this->client->get($endpoint);
			$body = (string)$response->getBody();
			$responseData = json_decode($body, true);

			if (!is_array($responseData)) {
				return 'not_connected';
			}

			if (($responseData['success'] ?? null) === false) {
				$msg = $responseData['message'] ?? '';
				return in_array($msg, ['session_not_found', 'session_not_connected'], true)
					? $msg
					: 'not_connected';
			}

			return (string)($responseData['state'] ?? 'not_connected');
		} catch (ClientException $e) {
			return 'not_connected';
		} catch (RequestException $e) {
			$this->logger->info('Could not connect to ' . $endpoint, ['exception' => $e]);
			throw new \Exception('Could not connect to the WhatsApp API. Please check the URL.', 1);
		}
	}

	/**
	 * @return int 0 = not connected, 1 = started, 2 = connected
	 */
	private function startSession(): int {
		$status = $this->getSessionStatus();
		return match ($status) {
			'CONNECTED' => 2,
			'session_not_connected' => 0,
			'session_not_found' => $this->getSessionStart(),
			default => 0,
		};
	}

	private function getSessionStart(): int {
		$endpoint = $this->getBaseUrl() . '/session/start/' . $this->instanceId;
		try {
			$this->client->get($endpoint);
		} catch (ClientException $e) {
			return 1;
		} catch (RequestException $e) {
			$this->logger->info('Could not connect to ' . $endpoint, [
				'exception' => $e,
			]);
			throw new \Exception('Could not connect to the WhatsApp API. Please check the URL.', 1);
		}
		return 0;
	}
}
