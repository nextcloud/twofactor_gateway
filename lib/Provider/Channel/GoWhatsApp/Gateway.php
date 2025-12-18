<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\GoWhatsApp;

use GuzzleHttp\Exception\RequestException;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * @method string getBaseUrl()
 * @method static setBaseUrl(string $baseUrl)
 * @method string getPhone()
 * @method static setPhone(string $phone)
 * @method string getUsername()
 * @method static setUsername(string $username)
 * @method string getPassword()
 * @method static setPassword(string $password)
 */
class Gateway extends AGateway {
	private IClient $client;
	private string $lazyBaseUrl = '';
	private string $lazyPhone = '';
	private string $lazyUsername = '';
	private string $lazyPassword = '';

	public function __construct(
		public IAppConfig $appConfig,
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appConfig);
		$this->client = $this->clientService->newClient();
	}

	#[\Override]
	public function createSettings(): Settings {
		return new Settings(
			name: 'GoWhatsApp',
			allowMarkdown: true,
			fields: [
				new FieldDefinition(
					field: 'base_url',
					prompt: 'Base URL to your WhatsApp API endpoint:',
				),
				new FieldDefinition(
					field: 'username',
					prompt: 'API Username:',
				),
				new FieldDefinition(
					field: 'password',
					prompt: 'API Password:',
				),
				new FieldDefinition(
					field: 'phone',
					prompt: 'Phone number for WhatsApp Web access:',
				),
			],
		);
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		$this->logger->debug("sending whatsapp message to $identifier, message: $message");

		$isOnWhatsApp = $this->checkUserOnWhatsApp($identifier);
		if (!$isOnWhatsApp) {
			throw new MessageTransmissionException('The phone number is not registered on WhatsApp.');
		}

		$phone = $this->formatPhoneNumber($identifier);

		try {
			$response = $this->client->post($this->getBaseUrl() . '/send/message', [
				'json' => [
					'phone' => $phone,
					'message' => $message,
				],
				'auth' => $this->getBasicAuth(),
			]);

			$body = (string)$response->getBody();
			$data = json_decode($body, true);

			if (($data['code'] ?? '') !== 'SUCCESS') {
				throw new MessageTransmissionException($data['message'] ?? 'Failed to send message');
			}

			$this->logger->debug("whatsapp message to $identifier sent successfully", [
				'message_id' => $data['results']['message_id'] ?? null,
			]);
		} catch (\Exception $e) {
			$this->logger->error('Could not send WhatsApp message', [
				'identifier' => $identifier,
				'exception' => $e,
			]);
			throw new MessageTransmissionException('Failed to send WhatsApp message: ' . $e->getMessage());
		}
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$helper = new QuestionHelper();

		$baseUrlQuestion = new Question($this->getSettings()->fields[0]->prompt . ' ');
		$this->lazyBaseUrl = $helper->ask($input, $output, $baseUrlQuestion);
		$this->lazyBaseUrl = rtrim($this->lazyBaseUrl, '/');

		$usernameQuestion = new Question($this->getSettings()->fields[1]->prompt . ' ');
		$this->lazyUsername = $helper->ask($input, $output, $usernameQuestion);

		$passwordQuestion = new Question($this->getSettings()->fields[2]->prompt . ' ');
		$passwordQuestion->setHidden(true);
		$passwordQuestion->setHiddenFallback(false);
		$this->lazyPassword = $helper->ask($input, $output, $passwordQuestion);

		$phoneQuestion = new Question($this->getSettings()->fields[3]->prompt . ' ');
		$this->lazyPhone = $helper->ask($input, $output, $phoneQuestion);
		$this->lazyPhone = preg_replace('/\D/', '', $this->lazyPhone);

		try {
			$output->writeln('<info>Requesting pairing code...</info>');
			$response = $this->client->get($this->getBaseUrl() . '/app/login-with-code', [
				'query' => ['phone' => $this->lazyPhone],
			]);

			$body = (string)$response->getBody();
			$data = json_decode($body, true);

			if (($data['code'] ?? '') !== 'SUCCESS') {
				$output->writeln('<error>' . ($data['message'] ?? 'Failed to get pairing code') . '</error>');
				return 1;
			}

			$pairCode = $data['results']['pair_code'] ?? '';
			if (empty($pairCode)) {
				$output->writeln('<error>No pairing code received</error>');
				return 1;
			}

			$output->writeln('');
			$output->writeln('<info>════════════════════════════════════</info>');
			$output->writeln('<info>    PAIRING CODE: ' . $pairCode . '</info>');
			$output->writeln('<info>════════════════════════════════════</info>');
			$output->writeln('');
			$output->writeln('Open WhatsApp on your phone and enter this code:');
			$output->writeln('1. Open WhatsApp');
			$output->writeln('2. Tap Menu or Settings');
			$output->writeln('3. Tap Linked Devices');
			$output->writeln('4. Tap Link a Device');
			$output->writeln('5. Select "Link with phone number instead"');
			$output->writeln('6. Enter the code: <comment>' . $pairCode . '</comment>');
			$output->writeln('');

			$output->writeln('<info>Waiting for confirmation...</info>');
			$maxAttempts = 60;
			$attempt = 0;

			while ($attempt < $maxAttempts) {
				sleep(2);

				try {
					$userInfoResponse = $this->client->get($this->getBaseUrl() . '/user/info', [
						'query' => ['phone' => $this->lazyPhone . '@s.whatsapp.net'],
					]);

					$userInfoBody = (string)$userInfoResponse->getBody();
					$userInfoData = json_decode($userInfoBody, true);

					if (($userInfoData['code'] ?? '') === 'SUCCESS') {
						$output->writeln('');
						$output->writeln('<info>✓ Successfully connected to WhatsApp!</info>');
						$output->writeln('');

						$results = $userInfoData['results'] ?? [];
						if (!empty($results['verified_name'])) {
							$output->writeln('<comment>Verified Name:</comment> ' . $results['verified_name']);
						}
						if (!empty($results['status'])) {
							$output->writeln('<comment>Status:</comment> ' . $results['status']);
						}
						if (!empty($results['devices']) && is_array($results['devices'])) {
							$deviceCount = count($results['devices']);
							$output->writeln('<comment>Connected Devices:</comment> ' . $deviceCount);
						}
						$output->writeln('');

						$this->setBaseUrl($this->lazyBaseUrl);
						$this->setUsername($this->lazyUsername);
						$this->setPassword($this->lazyPassword);
						$this->setPhone($this->lazyPhone);
						return 0;
					}
				} catch (\Exception $e) {
				}

				$attempt++;
				if ($attempt % 5 === 0) {
					$output->write('.');
				}
			}

			$output->writeln('');
			$output->writeln('<error>Timeout waiting for WhatsApp confirmation</error>');
			return 1;

		} catch (RequestException $e) {
			$output->writeln('<error>Could not connect to the WhatsApp API. Please check the URL.</error>');
			$this->logger->error('API connection error', ['exception' => $e]);
			return 1;
		} catch (\Exception $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			$this->logger->error('Configuration error', ['exception' => $e]);
			return 1;
		}
	}

	private function formatPhoneNumber(string $phoneNumber): string {
		$phone = preg_replace('/\D/', '', $phoneNumber);
		return $phone . '@s.whatsapp.net';
	}

	private function checkUserOnWhatsApp(string $phoneNumber): bool {
		try {
			$phone = preg_replace('/\D/', '', $phoneNumber);
			$response = $this->client->get($this->getBaseUrl() . '/user/check', [
				'query' => ['phone' => $phone],
				'auth' => $this->getBasicAuth(),
			]);

			$body = (string)$response->getBody();
			$data = json_decode($body, true);

			return ($data['code'] ?? '') === 'SUCCESS'
				&& ($data['results']['is_on_whatsapp'] ?? false) === true;
		} catch (\Exception $e) {
			$this->logger->error('Error checking if user is on WhatsApp', [
				'phone' => $phoneNumber,
				'exception' => $e,
			]);
			return false;
		}
	}

	private function getBasicAuth(): array {
		try {
			$username = $this->lazyUsername ?: $this->getUsername();
			$password = $this->lazyPassword ?: $this->getPassword();
			return [$username, $password];
		} catch (\Exception $e) {
			return ['', ''];
		}
	}

	private function getBaseUrl(): string {
		if ($this->lazyBaseUrl !== '') {
			return $this->lazyBaseUrl;
		}
		/** @var string */
		$this->lazyBaseUrl = parent::__call('getBaseUrl', []);
		return $this->lazyBaseUrl;
	}
}
