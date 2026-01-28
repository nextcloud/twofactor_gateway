<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\GoWhatsApp;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use OCA\TwoFactorGateway\Events\WhatsAppAuthenticationErrorEvent;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
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
	private const CODE_NOT_ON_WHATSAPP = 1001;
	private const CODE_AUTHENTICATION = 1401;
	private const CODE_FORBIDDEN = 1403;
	private const CODE_VERIFY_FAILED = 1500;
	private const CODE_SEND_FAILED = 2001;
	private const CODE_SEND_UNKNOWN = 2002;

	private const CONFIG_SUCCESS = 0;
	private const CONFIG_ERROR = 1;
	private const CONFIG_CONTINUE = 2;

	private IClient $client;
	private string $lazyBaseUrl = '';
	private string $lazyPhone = '';
	private string $lazyUsername = '';
	private string $lazyPassword = '';

	public function __construct(
		public IAppConfig $appConfig,
		private IClientService $clientService,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private IEventDispatcher $eventDispatcher,
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

		try {
			$isOnWhatsApp = $this->checkUserOnWhatsApp($identifier);
			if (!$isOnWhatsApp) {
				throw new MessageTransmissionException(
					$this->l10n->t('The phone number is not registered on WhatsApp.'),
					self::CODE_NOT_ON_WHATSAPP,
				);
			}
		} catch (MessageTransmissionException $e) {
			$this->logger->error('Could not verify WhatsApp user', [
				'identifier' => $identifier,
				'exception' => $e,
			]);
			throw $e;
		} catch (\Exception $e) {
			$this->logger->error('Could not verify WhatsApp user', [
				'identifier' => $identifier,
				'exception' => $e,
			]);
			throw new MessageTransmissionException(
				$this->l10n->t('Failed to verify WhatsApp user.'),
				self::CODE_VERIFY_FAILED,
			);
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
				throw new MessageTransmissionException(
					$data['message'] ?? $this->l10n->t('Failed to send message'),
					self::CODE_SEND_FAILED,
				);
			}

			$this->logger->debug("whatsapp message to $identifier sent successfully", [
				'message_id' => $data['results']['message_id'] ?? null,
			]);
		} catch (MessageTransmissionException $e) {
			$this->logger->error('Could not send WhatsApp message', [
				'identifier' => $identifier,
				'exception' => $e,
			]);
			throw $e;
		} catch (\Exception $e) {
			$this->logger->error('Could not send WhatsApp message', [
				'identifier' => $identifier,
				'exception' => $e,
			]);
			throw new MessageTransmissionException('Failed to send WhatsApp message: ' . $e->getMessage(), self::CODE_SEND_UNKNOWN);
		}
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$helper = new QuestionHelper();

		if (!$this->collectAndValidateBaseUrl($input, $output, $helper)) {
			return 1;
		}

		$result = $this->tryReuseExistingDevice($input, $output, $helper);
		if ($result !== self::CONFIG_CONTINUE) {
			return $result;
		}

		$this->collectCredentials($input, $output, $helper);

		return $this->setupNewDeviceWithPairingCode($input, $output, $helper);
	}

	private function collectAndValidateBaseUrl(InputInterface $input, OutputInterface $output, QuestionHelper $helper): bool {
		while (true) {
			$baseUrlQuestion = new Question($this->getSettings()->fields[0]->prompt . ' ');
			$this->lazyBaseUrl = $helper->ask($input, $output, $baseUrlQuestion);
			$this->lazyBaseUrl = rtrim($this->lazyBaseUrl, '/');

			$output->writeln('<info>Testing connection to API...</info>');
			if ($this->validateUrlReachability($output)) {
				return true;
			}

			$retryQuestion = new ConfirmationQuestion(
				'<question>Do you want to try with a different URL? [y/N] </question>',
				false
			);

			if (!$helper->ask($input, $output, $retryQuestion)) {
				return false;
			}

			$output->writeln('');
		}
	}

	private function tryReuseExistingDevice(InputInterface $input, OutputInterface $output, QuestionHelper $helper): int {
		$output->writeln('<info>Checking for existing devices...</info>');
		$devices = $this->fetchDevices();

		if ($devices === null || empty($devices)) {
			return self::CONFIG_CONTINUE;
		}

		$output->writeln('');
		$output->writeln('<info>Found ' . count($devices) . ' connected device(s):</info>');
		$this->displayDeviceList($output, $devices);

		$choice = $this->askDeviceAction($input, $output, $helper);

		if ($choice === 0) {
			return $this->configureWithDevice($output, $devices[0]);
		}

		if ($choice === 1) {
			$output->writeln('<info>Logging out device...</info>');
			$this->performLogout($output);
		}

		if ($choice === 2) {
			return self::CONFIG_ERROR;
		}

		return self::CONFIG_CONTINUE;
	}

	private function collectCredentials(InputInterface $input, OutputInterface $output, QuestionHelper $helper): void {
		$usernameQuestion = new Question($this->getSettings()->fields[1]->prompt . ' ');
		$this->lazyUsername = $helper->ask($input, $output, $usernameQuestion);

		$passwordQuestion = new Question($this->getSettings()->fields[2]->prompt . ' ');
		$passwordQuestion->setHidden(true);
		$passwordQuestion->setHiddenFallback(false);
		$this->lazyPassword = $helper->ask($input, $output, $passwordQuestion);
	}

	private function setupNewDeviceWithPairingCode(InputInterface $input, OutputInterface $output, QuestionHelper $helper): int {
		$phoneQuestion = new Question($this->getSettings()->fields[3]->prompt . ' ');
		$this->lazyPhone = $helper->ask($input, $output, $phoneQuestion);
		$this->lazyPhone = preg_replace('/\D/', '', $this->lazyPhone);

		$pairingCode = $this->requestPairingCode($input, $output);
		if ($pairingCode === null) {
			return self::CONFIG_ERROR;
		}

		return $this->displayPairingCodeAndWaitConfirmation($output, $pairingCode);
	}

	private function configureWithDevice(OutputInterface $output, array $device): int {
		$firstDevice = $device['device'] ?? '';
		preg_match('/^(\d+)/', $firstDevice, $matches);

		if (empty($matches[1])) {
			return self::CONFIG_CONTINUE;
		}

		$this->lazyPhone = $matches[1];

		$statusCheck = $this->checkDeviceStatusQuiet($firstDevice);
		if ($statusCheck) {
			$this->setBaseUrl($this->lazyBaseUrl);
			$this->setPhone($this->lazyPhone);
			$output->writeln('<info>✓ Configuration saved using existing device.</info>');
			$output->writeln('');
			$output->writeln('<comment>Base URL:</comment> ' . $this->lazyBaseUrl);
			$output->writeln('<comment>Phone:</comment> ' . $this->lazyPhone);
			$output->writeln('');
			return self::CONFIG_SUCCESS;
		}

		return self::CONFIG_CONTINUE;
	}

	private function configureWithDeviceAndAuth(InputInterface $input, OutputInterface $output, array $device): int {
		$firstDevice = $device['device'] ?? '';
		preg_match('/^(\d+)/', $firstDevice, $matches);

		if (empty($matches[1])) {
			return self::CONFIG_CONTINUE;
		}

		$this->lazyPhone = $matches[1];

		$output->writeln('<info>Checking device status...</info>');
		$statusCheck = $this->checkDeviceStatus($input, $output, $firstDevice);

		if ($statusCheck === self::CONFIG_SUCCESS) {
			$this->setBaseUrl($this->lazyBaseUrl);
			$this->setUsername($this->lazyUsername);
			$this->setPassword($this->lazyPassword);
			$this->setPhone($this->lazyPhone);
			$output->writeln('<info>✓ Configuration saved using existing device.</info>');
			$output->writeln('');
			$output->writeln('<comment>Base URL:</comment> ' . $this->lazyBaseUrl);
			$output->writeln('<comment>Username:</comment> ' . $this->lazyUsername);
			$output->writeln('<comment>Phone:</comment> ' . $this->lazyPhone);
			$output->writeln('');
			return self::CONFIG_SUCCESS;
		}

		if ($statusCheck === self::CONFIG_ERROR) {
			return self::CONFIG_ERROR;
		}

		return self::CONFIG_CONTINUE;
	}

	private function displayPairingCodeAndWaitConfirmation(OutputInterface $output, string $pairCode): int {
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

		return $this->pollForPairingConfirmation($output);
	}

	private function pollForPairingConfirmation(OutputInterface $output): int {
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

					$results = $userInfoData['results'] ?? [];
					$this->displayUserInfo($output, $results);

					$this->setBaseUrl($this->lazyBaseUrl);
					$this->setUsername($this->lazyUsername);
					$this->setPassword($this->lazyPassword);
					$this->setPhone($this->lazyPhone);
					return 0;
				}
			} catch (\Exception) {
			}

			$attempt++;
			if ($attempt % 5 === 0) {
				$output->write('.');
			}
		}

		$output->writeln('');
		$output->writeln('<error>Timeout waiting for WhatsApp confirmation</error>');
		return self::CONFIG_ERROR;
	}

	private function displayDeviceList(OutputInterface $output, array $devices): void {
		foreach ($devices as $index => $device) {
			$name = $device['name'] ?? 'Unknown';
			$deviceId = $device['device'] ?? 'Unknown';
			$output->writeln('  ' . ($index + 1) . '. <comment>' . $name . '</comment> (' . $deviceId . ')');
		}
		$output->writeln('');
	}

	private function displayDeviceInfo(OutputInterface $output, array $userInfo): void {
		$output->writeln('<info>✓ Device is connected and working.</info>');
		if (!empty($userInfo['verified_name'])) {
			$output->writeln('<comment>Account:</comment> ' . $userInfo['verified_name']);
		}
		if (!empty($userInfo['devices']) && is_array($userInfo['devices'])) {
			$deviceCount = count($userInfo['devices']);
			$output->writeln('<comment>Linked Devices:</comment> ' . $deviceCount);
		}
	}

	private function displayUserInfo(OutputInterface $output, array $results): void {
		$output->writeln('');
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
	}

	private function checkDeviceStatusQuiet(string $deviceJid): bool {
		$userInfo = $this->fetchUserInfo($deviceJid);
		return $userInfo !== null;
	}

	private function checkDeviceStatus(InputInterface $input, OutputInterface $output, string $deviceJid): int {
		$userInfo = $this->fetchUserInfo($deviceJid);

		if ($userInfo !== null) {
			$this->displayDeviceInfo($output, $userInfo);
			return self::CONFIG_SUCCESS;
		}

		$output->writeln('<error>Device is not responding or not logged in.</error>');
		return $this->handleDeviceIssue($input, $output);
	}

	private function fetchUserInfo(string $deviceJid): ?array {
		try {
			$response = $this->client->get($this->getBaseUrl() . '/user/info', [
				'query' => ['phone' => $deviceJid],
				'auth' => $this->getBasicAuth(),
			]);

			$body = (string)$response->getBody();
			$data = json_decode($body, true);

			if (($data['code'] ?? '') === 'SUCCESS') {
				return $data['results'] ?? [];
			}

			return null;
		} catch (\Exception $e) {
			$this->logger->error('Failed to fetch user info', ['exception' => $e]);
			return null;
		}
	}

	private function fetchDevices(): ?array {
		try {
			$response = $this->client->get($this->getBaseUrl() . '/app/devices', [
				'timeout' => 5,
			]);

			$body = (string)$response->getBody();
			$data = json_decode($body, true);

			if (($data['code'] ?? '') === 'SUCCESS' && isset($data['data'])) {
				return $data['data'];
			}

			if (($data['code'] ?? '') === 'SUCCESS' && isset($data['results'])) {
				return $data['results'];
			}

			return [];
		} catch (\Exception) {
			return null;
		}
	}

	private function requestPairingCode(InputInterface $input, OutputInterface $output): ?string {
		$output->writeln('<info>Requesting pairing code...</info>');

		$result = $this->fetchPairingCode();

		if (!$result['success'] && !$result['alreadyLoggedIn']) {
			$output->writeln('<error>Could not connect to the WhatsApp API. Please check the URL.</error>');
			return null;
		}

		if ($result['alreadyLoggedIn']) {
			$configResult = $this->handleAlreadyLoggedIn($input, $output);
			if ($configResult !== null) {
				return null;
			}
			$result = $this->fetchPairingCode();
			if (!$result['success']) {
				$output->writeln('<error>Could not get pairing code after logout.</error>');
				return null;
			}
		}

		return $result['code'];
	}

	private function fetchPairingCode(): array {
		try {
			$response = $this->client->get($this->getBaseUrl() . '/app/login-with-code', [
				'query' => ['phone' => $this->lazyPhone],
			]);

			$body = (string)$response->getBody();
			$data = json_decode($body, true);

			if (($data['code'] ?? '') !== 'SUCCESS') {
				return ['success' => false, 'code' => null, 'alreadyLoggedIn' => false];
			}

			$pairCode = $data['results']['pair_code'] ?? '';
			if (empty($pairCode)) {
				return ['success' => false, 'code' => null, 'alreadyLoggedIn' => false];
			}

			return ['success' => true, 'code' => $pairCode, 'alreadyLoggedIn' => false];
		} catch (BadResponseException $e) {
			if ($e->getCode() === 400) {
				$body = (string)$e->getResponse()->getBody();
				$data = json_decode($body, true);

				if (($data['code'] ?? '') === 'ALREADY_LOGGED_IN') {
					return ['success' => false, 'code' => null, 'alreadyLoggedIn' => true];
				}
			}
			$this->logger->error('API connection error', ['exception' => $e]);
			return ['success' => false, 'code' => null, 'alreadyLoggedIn' => false];
		} catch (RequestException $e) {
			$this->logger->error('API connection error', ['exception' => $e]);
			return ['success' => false, 'code' => null, 'alreadyLoggedIn' => false];
		}
	}

	private function handleAlreadyLoggedIn(InputInterface $input, OutputInterface $output): int {
		$output->writeln('<info>Account is already logged in.</info>');

		$userInfo = $this->fetchUserInfo($this->lazyPhone . '@s.whatsapp.net');
		if ($userInfo !== null && !empty($userInfo['verified_name'])) {
			$output->writeln('<comment>Current Account:</comment> ' . $userInfo['verified_name']);
		}

		$output->writeln('');
		$helper = new QuestionHelper();
		$continueQuestion = new ConfirmationQuestion(
			'Do you want to continue with the current session? [y/N] ',
			false
		);

		if ($helper->ask($input, $output, $continueQuestion)) {
			$this->setBaseUrl($this->lazyBaseUrl);
			$this->setUsername($this->lazyUsername);
			$this->setPassword($this->lazyPassword);
			$this->setPhone($this->lazyPhone);
			$output->writeln('<info>Configuration saved successfully.</info>');
			return self::CONFIG_SUCCESS;
		}

		$logoutSuccess = $this->performLogout($output);
		return $logoutSuccess ? self::CONFIG_CONTINUE : self::CONFIG_ERROR;
	}

	private function handleDeviceIssue(InputInterface $input, OutputInterface $output): int {
		$output->writeln('');
		$output->writeln('<comment>The device appears to have connection issues.</comment>');
		$output->writeln('<comment>Options:</comment>');
		$output->writeln('  1. Logout this device and set up a new one');
		$output->writeln('  2. Cancel and troubleshoot manually');
		$output->writeln('');

		$helper = new QuestionHelper();
		$logoutQuestion = new ConfirmationQuestion(
			'Do you want to logout this device and continue? [y/N] ',
			false
		);

		if ($helper->ask($input, $output, $logoutQuestion)) {
			$logoutSuccess = $this->performLogout($output);
			return $logoutSuccess ? self::CONFIG_CONTINUE : self::CONFIG_ERROR;
		}

		return self::CONFIG_ERROR;
	}

	private function performLogout(OutputInterface $output): bool {
		$output->writeln('<info>Logging out device...</info>');
		try {
			$this->client->get($this->getBaseUrl() . '/app/logout');
			$output->writeln('<info>Device logged out successfully. Please set up a new device.</info>');
			return true;
		} catch (\Exception $e) {
			$output->writeln('<error>Could not logout device. Please try manually.</error>');
			$this->logger->error('Logout failed', ['exception' => $e]);
			return false;
		}
	}

	private function validateUrlReachability(OutputInterface $output): bool {
		try {
			$response = $this->client->get($this->lazyBaseUrl . '/app/status', [
				'timeout' => 5,
			]);
			$output->writeln('<info>✓ API is reachable.</info>');
			return true;
		} catch (\GuzzleHttp\Exception\ConnectException $e) {
			$errorMessage = $e->getMessage();

			if (str_contains($errorMessage, 'Could not resolve host')) {
				$output->writeln('<error>✗ Could not resolve host. Please check the URL.</error>');
				$output->writeln('<comment>Make sure the URL is correct and accessible.</comment>');
			} elseif (str_contains($errorMessage, 'Connection refused')) {
				$output->writeln('<error>✗ Connection refused. The service might be down.</error>');
			} elseif (str_contains($errorMessage, 'Connection timed out')) {
				$output->writeln('<error>✗ Connection timed out. The service might be unreachable.</error>');
			} else {
				$output->writeln('<error>✗ Failed to connect to API.</error>');
				$output->writeln('<comment>Error: ' . $errorMessage . '</comment>');
			}

			$this->logger->error('Failed to validate URL reachability', [
				'url' => $this->lazyBaseUrl,
				'error' => $errorMessage,
			]);

			return false;
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
		} catch (BadResponseException|RequestException $e) {
			$status = $e->getResponse()?->getStatusCode();
			$body = (string)($e->getResponse()?->getBody() ?? '');
			$data = json_decode($body, true);

			if ($status === 401 || ($data['code'] ?? '') === 'AUTHENTICATION_ERROR') {
				$this->eventDispatcher->dispatchTyped(new WhatsAppAuthenticationErrorEvent());

				throw new MessageTransmissionException(
					$this->l10n->t('Authentication failed with WhatsApp API. Please verify username/password or log in again.'),
					self::CODE_AUTHENTICATION,
				);
			}

			if ($status === 403) {
				throw new MessageTransmissionException('Access to the WhatsApp API was denied (403). Check permissions or IP allowlist.', self::CODE_FORBIDDEN);
			}

			$this->logger->error('Error checking if user is on WhatsApp', [
				'phone' => $phoneNumber,
				'status' => $status,
				'response' => $body,
				'exception' => $e,
			]);

			$message = $data['message'] ?? $e->getMessage();
			throw new MessageTransmissionException(
				$this->l10n->t('Failed to verify WhatsApp user.'),
				self::CODE_VERIFY_FAILED,
			);
		} catch (\Exception $e) {
			$this->logger->error('Error checking if user is on WhatsApp', [
				'phone' => $phoneNumber,
				'exception' => $e,
			]);
			throw new MessageTransmissionException(
				$this->l10n->t('Failed to verify WhatsApp user.'),
				self::CODE_VERIFY_FAILED,
			);
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

	private function askDeviceAction(InputInterface $input, OutputInterface $output, QuestionHelper $helper): int {
		$actions = [
			'Use this device',
			'Try another device',
			'Abort configuration',
		];

		$question = new ChoiceQuestion(
			'What would you like to do?',
			$actions,
			0
		);
		$question->setErrorMessage('Invalid choice: %s');

		$choice = $helper->ask($input, $output, $question);

		return array_search($choice, $actions, true) !== false
			? (int)array_search($choice, $actions, true)
			: 0;
	}
}
