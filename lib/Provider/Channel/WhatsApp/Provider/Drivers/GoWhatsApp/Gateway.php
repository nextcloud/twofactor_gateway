<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\FieldType;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IConfigurationChangeAwareGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IDefaultInstanceAwareGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IInteractiveSetupGateway;
use OCA\TwoFactorGateway\Provider\Gateway\ITestResultEnricher;
use OCA\TwoFactorGateway\Provider\Settings;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Service\GoWhatsAppSessionMonitorJobManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * @method string getBaseUrl()
 * @method static setBaseUrl(string $baseUrl)
 * @method string getPhone()
 * @method static setPhone(string $phone)
 * @method string getDeviceName()
 * @method static setDeviceName(string $deviceName)
 * @method string getDeviceId()
 * @method static setDeviceId(string $deviceId)
 * @method string getUsername()
 * @method static setUsername(string $username)
 * @method string getPassword()
 * @method static setPassword(string $password)
 */
class Gateway extends AGateway implements IConfigurationChangeAwareGateway, IInteractiveSetupGateway, IDefaultInstanceAwareGateway, ITestResultEnricher {
	use GatewayCliSetupTrait;

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
	private string $lazyDeviceName = '';
	private string $lazyDeviceId = '';
	private InteractiveSetupStateStore $interactiveSetupStateStore;

	public function __construct(
		public IAppConfig $appConfig,
		private IClientService $clientService,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private IEventDispatcher $eventDispatcher,
		private GoWhatsAppSessionMonitorJobManager $goWhatsAppSessionMonitorJobManager,
		?InteractiveSetupStateStore $interactiveSetupStateStore = null,
	) {
		parent::__construct($appConfig);
		$this->client = $this->clientService->newClient();
		$this->interactiveSetupStateStore = $interactiveSetupStateStore ?? new InteractiveSetupStateStore($this->appConfig);
	}

	#[\Override]
	public function syncAfterConfigurationChange(): void {
		$this->goWhatsAppSessionMonitorJobManager->sync();
	}

	#[\Override]
	public function createSettings(): Settings {
		return new Settings(
			name: 'WhatsApp web',
			id: 'gowhatsapp',
			allowMarkdown: true,
			fields: [
				new FieldDefinition(
					field: 'base_url',
					prompt: 'Base URL to your WhatsApp API endpoint:',
				),
				new FieldDefinition(
					field: 'phone',
					prompt: 'Phone number for WhatsApp Web access:',
				),
				new FieldDefinition(
					field: 'device_name',
					prompt: 'Device name shown in WhatsApp linked devices:',
					default: 'TwoFactor Gateway',
					optional: true,
				),
				new FieldDefinition(
					field: 'device_id',
					prompt: 'Device ID (auto-generated, do not edit):',
					optional: true,
					hidden: true,
				),
				new FieldDefinition(
					field: 'username',
					prompt: 'API Username:',
					optional: true,
				),
				new FieldDefinition(
					field: 'password',
					prompt: 'API Password:',
					optional: true,
					type: FieldType::SECRET,
				),
				new FieldDefinition(
					field: 'webhook_hybrid_enabled',
					prompt: 'Enable hybrid monitoring webhook:',
					default: '0',
					optional: true,
					type: FieldType::BOOLEAN,
				),
				new FieldDefinition(
					field: 'webhook_secret',
					prompt: 'Webhook HMAC secret for X-Hub-Signature-256:',
					optional: true,
					type: FieldType::SECRET,
				),
				new FieldDefinition(
					field: 'webhook_min_check_interval',
					prompt: 'Minimum seconds between webhook-triggered checks:',
					default: '30',
					optional: true,
					type: FieldType::INTEGER,
					min: 0,
					max: 3600,
				),
			],
		);
	}

	#[\Override]
	public function getProviderId(): string {
		return 'gowhatsapp';
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
			$options = [
				'json' => [
					'phone' => $phone,
					'message' => $message,
				],
				'auth' => $this->getBasicAuth(),
			];

			$deviceId = $this->getDeviceId();
			if (!empty($deviceId)) {
				$options['headers'] = ['X-Device-Id' => $deviceId];
			}

			$response = $this->client->post($this->getBaseUrl() . '/send/message', $options);

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

		$this->collectCredentials($input, $output, $helper);

		$result = $this->tryReuseExistingDevice($input, $output, $helper);
		if ($result !== self::CONFIG_CONTINUE) {
			return $result;
		}

		// Only collect device name if we need to create a new device
		if ($this->lazyDeviceName === '') {
			$this->collectDeviceName($input, $output, $helper);
		}

		return $this->setupNewDeviceWithPairingCode($input, $output, $helper);
	}

	/**
	 * @param array<string, string> $input
	 * @return array<string, mixed>
	 */
	#[\Override]
	public function interactiveSetupStart(array $input): array {
		$baseUrl = rtrim(trim((string)($input['base_url'] ?? '')), '/');
		if ($baseUrl === '') {
			return $this->withMessageType([
				'status' => 'error',
				'message' => 'Base URL is required to start interactive setup.',
			]);
		}

		$sessionId = $this->interactiveSetupStateStore->createSessionId();
		$state = [
			'base_url' => $baseUrl,
			'username' => trim((string)($input['username'] ?? '')),
			'password' => (string)($input['password'] ?? ''),
			'device_name' => trim((string)($input['device_name'] ?? '')),
			'device_id' => '',
			'phone' => '',
		];

		$this->hydrateFromSetupState($state);
		$urlValidation = $this->validateUrlReachabilityForWeb();
		if (!$urlValidation['ok']) {
			return $this->withMessageType([
				'status' => 'error',
				'message' => $urlValidation['message'],
			]);
		}

		$this->saveSetupState($sessionId, $state);
		$devices = $this->fetchDevices() ?? [];

		if ($devices === []) {
			return $this->withMessageType([
				'status' => 'needs_input',
				'sessionId' => $sessionId,
				'step' => 'phone',
				'message' => 'No device was found. Continue by informing a phone number to link a new device.',
			]);
		}

		return $this->withMessageType([
			'status' => 'needs_input',
			'sessionId' => $sessionId,
			'step' => 'device_choice',
			'message' => 'Existing devices were found. Choose how to continue setup.',
			'data' => [
				'devices' => array_map(static function (array $device): array {
					return [
						'id' => (string)($device['id'] ?? ''),
						'display_name' => (string)($device['display_name'] ?? ''),
						'phone_number' => (string)($device['phone_number'] ?? ''),
						'state' => (string)($device['state'] ?? ''),
					];
				}, $devices),
			],
		]);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	#[\Override]
	public function interactiveSetupStep(string $sessionId, string $action, array $input = []): array {
		$state = $this->interactiveSetupStateStore->load($sessionId);
		if ($state === null) {
			return $this->withMessageType([
				'status' => 'error',
				'message' => 'Interactive setup session was not found or expired.',
			]);
		}

		$this->hydrateFromSetupState($state);

		return $this->withMessageType(match ($action) {
			'choose_device' => $this->interactiveSetupChooseDevice($sessionId, $state, $input),
			'submit_phone' => $this->interactiveSetupSubmitPhone($sessionId, $state, $input),
			'poll_pairing' => $this->interactiveSetupPollPairing($sessionId, $state),
			'cancel' => $this->interactiveSetupCancel($sessionId),
			default => [
				'status' => 'error',
				'message' => 'Unknown setup action: ' . $action,
			],
		});
	}

	/**
	 * @return array<string, mixed>
	 */
	#[\Override]
	public function interactiveSetupCancel(string $sessionId): array {
		$this->interactiveSetupStateStore->delete($sessionId);
		return $this->withMessageType([
			'status' => 'cancelled',
			'message' => 'Interactive setup cancelled.',
		]);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function withMessageType(array $payload): array {
		if (!isset($payload['messageType']) && isset($payload['status'])) {
			$payload['messageType'] = match ((string)$payload['status']) {
				'done' => 'success',
				'error' => 'error',
				'needs_input', 'pending', 'cancelled' => 'info',
				default => 'info',
			};
		}

		return $payload;
	}

	private function collectDeviceName(InputInterface $input, OutputInterface $output, QuestionHelper $helper): void {
		$defaultDeviceName = $this->getDeviceNameValue();
		$deviceNameQuestion = new Question($this->getFieldPrompt('device_name') . ' ', $defaultDeviceName);
		$deviceName = (string)$helper->ask($input, $output, $deviceNameQuestion);
		$deviceName = trim($deviceName);
		$this->lazyDeviceName = $deviceName !== '' ? $deviceName : $defaultDeviceName;
	}

	private function collectAndValidateBaseUrl(InputInterface $input, OutputInterface $output, QuestionHelper $helper): bool {
		while (true) {
			$baseUrlQuestion = new Question($this->getFieldPrompt('base_url') . ' ');
			$this->lazyBaseUrl = (string)($helper->ask($input, $output, $baseUrlQuestion) ?? '');
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
		$allDevices = $this->fetchDevices();


		if (empty($allDevices)) {
			$output->writeln('<info>No devices found. Creating a new device...</info>');
			return self::CONFIG_CONTINUE;
		}

		$output->writeln('');
		$output->writeln('<info>Found ' . count($allDevices) . ' device(s):</info>');
		$this->displayDeviceList($output, $allDevices);

		// Ask user what to do with existing device(s)
		$output->writeln('');
		$choice = $this->askDeviceChoiceAction($input, $output, $helper);

		if ($choice === 0) {
			// Use existing device
			$device = reset($allDevices);
			$output->writeln('<info>Using device: ' . ($device['display_name'] ?? $device['id'] ?? '') . '</info>');

			// Logout other devices if multiple exist
			if (count($allDevices) > 1) {
				$output->writeln('<info>Logging out other devices...</info>');
				foreach ($allDevices as $otherDevice) {
					if (($otherDevice['id'] ?? '') !== ($device['id'] ?? '')) {
						$this->logoutDevice($output, $otherDevice);
					}
				}
			}

			return $this->configureWithDeviceAndAuth($input, $output, $device);
		}

		if ($choice === 1) {
			// Logout all and create new
			$this->collectDeviceName($input, $output, $helper);
			$output->writeln('<info>Logging out all devices...</info>');
			$this->logoutAllDevices($output, $allDevices);
			$output->writeln('<info>Creating new device...</info>');
			if (!$this->createNewDevice($output)) {
				return self::CONFIG_ERROR;
			}
			return self::CONFIG_CONTINUE;
		}

		if ($choice === 2) {
			// Create new device (keep existing)
			$this->collectDeviceName($input, $output, $helper);
			$output->writeln('<info>Creating new device...</info>');
			if (!$this->createNewDevice($output)) {
				return self::CONFIG_ERROR;
			}
			return self::CONFIG_CONTINUE;
		}


		return self::CONFIG_CONTINUE;
	}

	private function collectCredentials(InputInterface $input, OutputInterface $output, QuestionHelper $helper): void {
		$usernameQuestion = new Question($this->getFieldPrompt('username') . ' ');
		$this->lazyUsername = (string)($helper->ask($input, $output, $usernameQuestion) ?? '');

		$passwordQuestion = new Question($this->getFieldPrompt('password') . ' ');
		$passwordQuestion->setHidden(true);
		$passwordQuestion->setHiddenFallback(false);
		$this->lazyPassword = (string)($helper->ask($input, $output, $passwordQuestion) ?? '');
	}

	private function setupNewDeviceWithPairingCode(InputInterface $input, OutputInterface $output, QuestionHelper $helper): int {
		// Create a new device first if device_id is not already set
		if (empty($this->lazyDeviceId)) {
			$output->writeln('<info>Creating new device...</info>');
			if (!$this->createNewDevice($output)) {
				return self::CONFIG_ERROR;
			}
		}

		$phoneQuestion = new Question($this->getFieldPrompt('phone') . ' ');
		$this->lazyPhone = (string)($helper->ask($input, $output, $phoneQuestion) ?? '');
		$this->lazyPhone = (string)preg_replace('/\D/', '', $this->lazyPhone);

		$pairingCodeResult = $this->requestPairingCode($input, $output);
		if ($pairingCodeResult['status'] === self::CONFIG_SUCCESS) {
			return self::CONFIG_SUCCESS;
		}

		if ($pairingCodeResult['status'] === self::CONFIG_ERROR || $pairingCodeResult['code'] === null) {
			return self::CONFIG_ERROR;
		}

		return $this->displayPairingCodeAndWaitConfirmation($output, $pairingCodeResult['code']);
	}

	private function configureWithDevice(OutputInterface $output, array $device): int {
		// Device is an array with API v8 fields: id, jid, phone_number, display_name, state
		$deviceId = $device['id'] ?? '';
		$deviceJid = $device['jid'] ?? '';
		$phoneNumber = $device['phone_number'] ?? '';

		// Use device_id for API calls
		if (!empty($deviceId)) {
			$this->lazyDeviceId = $deviceId;
		} else {
			return self::CONFIG_CONTINUE;
		}

		// Extract phone number from jid (format: "phone@s.whatsapp.net") or use phone_number field
		if (!empty($deviceJid)) {
			preg_match('/^(\d+)@/', $deviceJid, $matches);
			if (!empty($matches[1])) {
				$this->lazyPhone = $matches[1];
			}
		} elseif (!empty($phoneNumber)) {
			// Fallback to phone_number field if available
			$this->lazyPhone = preg_replace('/\D/', '', $phoneNumber) ?? '';
		}

		if (empty($this->lazyPhone)) {
			return self::CONFIG_CONTINUE;
		}

		$statusCheck = $this->checkDeviceStatusQuiet($deviceJid ?: $this->lazyPhone . '@s.whatsapp.net');
		if ($statusCheck) {
			$this->setBaseUrl($this->lazyBaseUrl);
			$this->setPhone($this->lazyPhone);
			$this->setDeviceName($this->getDeviceNameValue());
			$this->setDeviceId($this->lazyDeviceId);
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
		// Device is an array with API v8 fields: id, jid, phone_number, display_name, state
		$deviceId = $device['id'] ?? '';
		$deviceJid = $device['jid'] ?? '';
		$phoneNumber = $device['phone_number'] ?? '';

		// Use device_id for API calls
		if (!empty($deviceId)) {
			$this->lazyDeviceId = $deviceId;
		} else {
			return self::CONFIG_CONTINUE;
		}

		// Extract phone number from jid (format: "phone@s.whatsapp.net") or use phone_number field
		if (!empty($deviceJid)) {
			preg_match('/^(\d+)@/', $deviceJid, $matches);
			if (!empty($matches[1])) {
				$this->lazyPhone = $matches[1];
			}
		} elseif (!empty($phoneNumber)) {
			// Fallback to phone_number field if available
			$this->lazyPhone = preg_replace('/\D/', '', $phoneNumber) ?? '';
		}

		if (empty($this->lazyPhone)) {
			return self::CONFIG_CONTINUE;
		}

		$output->writeln('<info>Checking device status...</info>');
		$statusCheck = $this->checkDeviceStatus($input, $output, $deviceJid ?: $this->lazyPhone . '@s.whatsapp.net');

		if ($statusCheck === self::CONFIG_SUCCESS) {
			$this->setBaseUrl($this->lazyBaseUrl);
			$this->setUsername($this->lazyUsername);
			$this->setPassword($this->lazyPassword);
			$this->setPhone($this->lazyPhone);
			$this->setDeviceName($this->getDeviceNameValue());
			$this->setDeviceId($this->lazyDeviceId);
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
				$options = [
					'query' => ['phone' => $this->lazyPhone . '@s.whatsapp.net'],
					'auth' => $this->getBasicAuth(),
				];

				// Add device_id as header if available
				$deviceId = $this->getDeviceId();
				if (!empty($deviceId)) {
					$options['headers'] = ['X-Device-Id' => $deviceId];
				}

				$userInfoResponse = $this->client->get($this->getBaseUrl() . '/user/info', $options);

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
					$this->setDeviceName($this->getDeviceNameValue());
					$this->setDeviceId($this->lazyDeviceId);
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

	/**
	 * @param array<string, mixed> $state
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function interactiveSetupChooseDevice(string $sessionId, array $state, array $input): array {
		$strategy = (string)($input['strategy'] ?? '');
		$devices = $this->fetchDevices() ?? [];

		if ($strategy === 'use_existing') {
			$requestedDeviceId = (string)($input['device_id'] ?? '');
			$device = $this->resolveDeviceForReuse($devices, $requestedDeviceId);
			if ($device === null) {
				return [
					'status' => 'error',
					'message' => 'No matching device was found to reuse.',
				];
			}

			if ($this->configureWithDeviceWeb($device)) {
				$this->interactiveSetupStateStore->delete($sessionId);
				return [
					'status' => 'done',
					'message' => 'Configuration saved using an existing connected device.',
					'config' => $this->buildCurrentConfigPayload(),
					'data' => $this->buildCurrentAccountDataPayload(),
				];
			}

			$state['device_id'] = (string)($device['id'] ?? '');
			$this->saveSetupState($sessionId, $state);
			return [
				'status' => 'needs_input',
				'sessionId' => $sessionId,
				'step' => 'phone',
				'message' => 'The selected device needs relinking. Provide a phone number to continue.',
			];
		}

		if ($strategy === 'logout_all_create_new') {
			$this->logoutAllDevices(new NullOutput(), $devices);
			if (!$this->createNewDevice(new NullOutput())) {
				return [
					'status' => 'error',
					'message' => 'Could not create a new device after logout.',
				];
			}

			$state['device_id'] = $this->lazyDeviceId;
			$this->saveSetupState($sessionId, $state);
			return [
				'status' => 'needs_input',
				'sessionId' => $sessionId,
				'step' => 'phone',
				'message' => 'All devices were logged out. Provide a phone number to link the new device.',
			];
		}

		if ($strategy === 'create_new_keep_existing') {
			if (!$this->createNewDevice(new NullOutput())) {
				return [
					'status' => 'error',
					'message' => 'Could not create a new device.',
				];
			}

			$state['device_id'] = $this->lazyDeviceId;
			$this->saveSetupState($sessionId, $state);
			return [
				'status' => 'needs_input',
				'sessionId' => $sessionId,
				'step' => 'phone',
				'message' => 'New device created. Provide a phone number to link it.',
			];
		}

		return [
			'status' => 'error',
			'message' => 'Invalid device strategy provided.',
		];
	}

	/**
	 * @param array<string, mixed> $state
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function interactiveSetupSubmitPhone(string $sessionId, array $state, array $input): array {
		$phone = (string)preg_replace('/\D/', '', (string)($input['phone'] ?? ''));
		if ($phone === '') {
			return [
				'status' => 'error',
				'message' => 'Phone number is required.',
			];
		}

		$this->lazyPhone = $phone;
		$state['phone'] = $phone;

		if ($this->lazyDeviceId === '' && !$this->createNewDevice(new NullOutput())) {
			return [
				'status' => 'error',
				'message' => 'Could not create a device for pairing.',
			];
		}

		$continueExisting = null;
		if (array_key_exists('continue_existing', $input)) {
			$continueExisting = (bool)$input['continue_existing'];
		}

		$pairingResult = $this->requestPairingCodeForWeb($continueExisting);
		if ($pairingResult['status'] === 'done') {
			$this->interactiveSetupStateStore->delete($sessionId);
			return [
				'status' => 'done',
				'message' => $pairingResult['message'],
				'config' => $this->buildCurrentConfigPayload(),
				'data' => $this->buildCurrentAccountDataPayload(),
			];
		}

		if ($pairingResult['status'] === 'error') {
			$this->saveSetupState($sessionId, $state);
			return [
				'status' => 'error',
				'message' => $pairingResult['message'],
			];
		}

		if ($pairingResult['step'] === 'already_logged_in') {
			$this->saveSetupState($sessionId, $state);
			return [
				'status' => 'needs_input',
				'sessionId' => $sessionId,
				'step' => 'already_logged_in',
				'message' => 'This phone is already logged in. Continue current session or force logout and relink.',
			];
		}

		$this->saveSetupState($sessionId, $state);
		return [
			'status' => 'pending',
			'sessionId' => $sessionId,
			'step' => 'pairing',
			'message' => 'Pairing code generated. Confirm it on your phone and poll status.',
			'data' => [
				'pair_code' => $pairingResult['pair_code'],
			],
		];
	}

	/**
	 * @param array<string, mixed> $state
	 * @return array<string, mixed>
	 */
	private function interactiveSetupPollPairing(string $sessionId, array $state): array {
		$paired = $this->pollPairingStatusOnceForWeb();
		if ($paired) {
			$this->interactiveSetupStateStore->delete($sessionId);
			return [
				'status' => 'done',
				'message' => 'Device paired successfully and configuration saved.',
				'config' => $this->buildCurrentConfigPayload(),
				'data' => $this->buildCurrentAccountDataPayload(),
			];
		}

		$this->saveSetupState($sessionId, $state);
		return [
			'status' => 'pending',
			'sessionId' => $sessionId,
			'step' => 'pairing',
			'message' => 'Not confirmed yet. Open WhatsApp on your phone, go to Linked devices and enter the pairing code shown above.',
		];
	}

	private function configureWithDeviceWeb(array $device): bool {
		$deviceId = (string)($device['id'] ?? '');
		if ($deviceId === '') {
			return false;
		}

		$this->lazyDeviceId = $deviceId;
		$deviceJid = (string)($device['jid'] ?? '');
		$phoneNumber = (string)($device['phone_number'] ?? '');

		if ($deviceJid !== '') {
			preg_match('/^(\d+)@/', $deviceJid, $matches);
			if (!empty($matches[1])) {
				$this->lazyPhone = $matches[1];
			}
		} elseif ($phoneNumber !== '') {
			$this->lazyPhone = preg_replace('/\D/', '', $phoneNumber) ?? '';
		}

		if ($this->lazyPhone === '') {
			return false;
		}

		$deviceJidToCheck = $deviceJid !== '' ? $deviceJid : $this->lazyPhone . '@s.whatsapp.net';
		if (!$this->checkDeviceStatusQuiet($deviceJidToCheck)) {
			return false;
		}

		$this->persistCurrentSetupConfiguration();
		return true;
	}

	/**
	 * @return array{status: string, message: string, step?: string, pair_code?: string}
	 */
	private function requestPairingCodeForWeb(?bool $continueExisting): array {
		$result = $this->fetchPairingCode();

		if (!$result['success'] && !$result['alreadyLoggedIn']) {
			$apiError = trim((string)($result['errorMessage'] ?? ''));
			$message = $apiError !== ''
				? $apiError
				: 'Could not connect to the WhatsApp API to request pairing code.';
			return [
				'status' => 'error',
				'message' => $message,
			];
		}

		if ($result['alreadyLoggedIn']) {
			if ($continueExisting === null) {
				return [
					'status' => 'needs_input',
					'step' => 'already_logged_in',
					'message' => 'Phone already logged in.',
				];
			}

			if ($continueExisting) {
				$this->persistCurrentSetupConfiguration();
				return [
					'status' => 'done',
					'message' => 'Configuration saved using the existing logged-in session.',
				];
			}

			if (!$this->performLogout(new NullOutput())) {
				return [
					'status' => 'error',
					'message' => 'Could not logout current session to continue pairing.',
				];
			}

			$result = $this->fetchPairingCode();
			if (!$result['success']) {
				return [
					'status' => 'error',
					'message' => 'Could not get pairing code after logout.',
				];
			}
		}

		return [
			'status' => 'pending',
			'message' => 'Pairing code generated.',
			'pair_code' => (string)$result['code'],
		];
	}

	private function pollPairingStatusOnceForWeb(): bool {
		if ($this->lazyPhone === '') {
			return false;
		}

		try {
			$options = [
				'query' => ['phone' => $this->lazyPhone . '@s.whatsapp.net'],
				'auth' => $this->getBasicAuth(),
			];

			$deviceId = $this->getDeviceId();
			if ($deviceId !== '') {
				$options['headers'] = ['X-Device-Id' => $deviceId];
			}

			$response = $this->client->get($this->getBaseUrl() . '/user/info', $options);
			$body = (string)$response->getBody();
			$data = json_decode($body, true);

			if (($data['code'] ?? '') !== 'SUCCESS') {
				return false;
			}

			$this->persistCurrentSetupConfiguration();
			return true;
		} catch (\Exception) {
			return false;
		}
	}

	private function persistCurrentSetupConfiguration(): void {
		$this->setBaseUrl($this->lazyBaseUrl);
		$this->setUsername($this->lazyUsername);
		$this->setPassword($this->lazyPassword);
		$this->setPhone($this->lazyPhone);
		$this->setDeviceName($this->getDeviceNameValue());
		$this->setDeviceId($this->lazyDeviceId);
		$this->clearRequiresReconfigureFlag();
	}

	private function clearRequiresReconfigureFlag(): void {
		ReconfigurationState::clear($this->appConfig);
	}

	#[\Override]
	public function onDefaultInstanceActivated(): void {
		$this->clearRequiresReconfigureFlag();
	}

	/**
	 * Provide account information about the configured WhatsApp account.
	 *
	 * Calls /user/info using the instance's own phone number so that the
	 * admin UI can display the account name (and optionally avatar, if the
	 * API returns a URL in the future).  A network or API failure returns
	 * an empty array so the controller degrades gracefully.
	 *
	 * @param array<string, string> $instanceConfig
	 * @param string $identifier
	 * @return array<string, string>
	 */
	#[\Override]
	public function enrichTestResult(array $instanceConfig, string $identifier = ''): array {
		$baseUrl = rtrim((string)($instanceConfig['base_url'] ?? ''), '/');
		$username = (string)($instanceConfig['username'] ?? '');
		$password = (string)($instanceConfig['password'] ?? '');
		$deviceId = (string)($instanceConfig['device_id'] ?? '');
		$lookupIdentifier = $this->buildUserInfoLookupIdentifier(
			$identifier !== '' ? $identifier : (string)($instanceConfig['phone'] ?? ''),
		);

		if ($baseUrl === '' || $lookupIdentifier === '') {
			return [];
		}

		try {
			$response = $this->client->get(
				$baseUrl . '/user/info',
				$this->buildUserLookupRequestOptions($lookupIdentifier, $username, $password, $deviceId),
			);
			$body = (string)$response->getBody();
			/** @var array<string, mixed>|null $data */
			$data = json_decode($body, true);

			if (($data['code'] ?? '') !== 'SUCCESS') {
				return [];
			}

			/** @var array<string, mixed> $results */
			$results = $data['results'] ?? [];
			if (!is_array($results)) {
				return [];
			}

			$firstUserInfo = $this->extractFirstUserInfoResult($results);
			if (!is_array($firstUserInfo)) {
				return [];
			}

			$avatarUrl = $this->fetchAvatarDataUri($baseUrl, $lookupIdentifier, $username, $password, $deviceId);

			return $this->extractAccountInfoFromUserInfo($firstUserInfo, $avatarUrl);
		} catch (\Exception) {
			return [];
		}
	}

	/** @return array<string, string> */
	private function buildCurrentConfigPayload(): array {
		return [
			'base_url' => $this->lazyBaseUrl,
			'username' => $this->lazyUsername,
			'password' => $this->lazyPassword,
			'phone' => $this->lazyPhone,
			'device_name' => $this->getDeviceNameValue(),
			'device_id' => $this->lazyDeviceId,
		];
	}

	/** @return array<string, mixed> */
	private function buildCurrentAccountDataPayload(): array {
		$accountInfo = $this->fetchCurrentAccountInfo();
		if ($accountInfo === []) {
			return [];
		}

		return ['account' => $accountInfo];
	}

	/** @return array<string, string> */
	private function fetchCurrentAccountInfo(): array {
		if ($this->lazyPhone === '') {
			return [];
		}

		$results = $this->fetchUserInfo($this->buildUserInfoLookupIdentifier($this->lazyPhone));
		if (!is_array($results)) {
			return [];
		}

		$avatarUrl = $this->fetchAvatarDataUri(
			$this->getBaseUrl(),
			$this->buildUserInfoLookupIdentifier($this->lazyPhone),
			$this->getBasicAuth()[0] ?? '',
			$this->getBasicAuth()[1] ?? '',
			$this->getDeviceId(),
		);

		return $this->extractAccountInfoFromUserInfo($results, $avatarUrl);
	}

	/** @param array<string, mixed> $results */
	private function extractFirstUserInfoResult(array $results): ?array {
		$dataArray = $results['data'] ?? null;
		if (is_array($dataArray)) {
			$firstResult = reset($dataArray);
			return is_array($firstResult) ? $firstResult : null;
		}

		return $results;
	}

	/** @param array<string, mixed> $userInfo */
	private function extractAccountInfoFromUserInfo(array $userInfo, string $avatarUrlOverride = ''): array {
		$accountName = trim((string)($userInfo['verified_name'] ?? $userInfo['push_name'] ?? ''));
		if ($accountName === '') {
			$accountName = trim((string)($userInfo['name'] ?? ''));
		}
		if ($accountName === '') {
			return [];
		}

		$account = ['account_name' => $accountName];
		$avatarUrl = trim($avatarUrlOverride);
		if ($avatarUrl === '') {
			$avatarUrl = trim((string)($userInfo['avatar_url'] ?? $userInfo['profile_picture_url'] ?? $userInfo['picture_url'] ?? ''));
		}
		if ($avatarUrl !== '') {
			$account['account_avatar_url'] = $avatarUrl;
		}

		return $account;
	}

	/** @return array<string, mixed> */
	private function buildUserLookupRequestOptions(string $lookupIdentifier, string $username = '', string $password = '', string $deviceId = '', bool $previewAvatar = false): array {
		$options = [
			'query' => ['phone' => $lookupIdentifier],
			'timeout' => 5,
		];
		if ($previewAvatar) {
			$options['query']['is_preview'] = 'true';
		}

		if ($username !== '' || $password !== '') {
			$options['auth'] = [$username, $password];
		}

		if ($deviceId !== '') {
			$options['headers'] = ['X-Device-Id' => $deviceId];
		}

		return $options;
	}

	private function fetchAvatarDataUri(string $baseUrl, string $lookupIdentifier, string $username = '', string $password = '', string $deviceId = ''): string {
		try {
			$response = $this->client->get(
				$baseUrl . '/user/avatar',
				$this->buildUserLookupRequestOptions($lookupIdentifier, $username, $password, $deviceId, true),
			);
			$body = (string)$response->getBody();
			/** @var array<string, mixed>|null $data */
			$data = json_decode($body, true);
			if (($data['code'] ?? '') !== 'SUCCESS') {
				return '';
			}

			$results = $data['results'] ?? [];
			if (!is_array($results)) {
				return '';
			}

			$avatarUrl = trim((string)($results['url'] ?? ''));
			if ($avatarUrl === '') {
				return '';
			}

			$avatarResponse = $this->client->get($avatarUrl, ['timeout' => 5]);
			$avatarBody = (string)$avatarResponse->getBody();
			if ($avatarBody === '') {
				return '';
			}

			return sprintf(
				'data:%s;base64,%s',
				$this->detectAvatarMimeType($avatarUrl),
				base64_encode($avatarBody),
			);
		} catch (\Exception) {
			return '';
		}
	}

	private function detectAvatarMimeType(string $avatarUrl): string {
		$path = parse_url($avatarUrl, PHP_URL_PATH);
		$extension = is_string($path) ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';

		return match ($extension) {
			'png' => 'image/png',
			'gif' => 'image/gif',
			'webp' => 'image/webp',
			'jpg', 'jpeg' => 'image/jpeg',
			default => 'image/jpeg',
		};
	}

	private function buildUserInfoLookupIdentifier(string $identifier): string {
		$normalizedIdentifier = trim($identifier);
		if ($normalizedIdentifier === '') {
			return '';
		}

		return str_contains($normalizedIdentifier, '@') ? $normalizedIdentifier : $normalizedIdentifier . '@s.whatsapp.net';
	}

	/** @param array<string, mixed> $state */
	private function hydrateFromSetupState(array $state): void {
		$this->lazyBaseUrl = (string)($state['base_url'] ?? '');
		$this->lazyUsername = (string)($state['username'] ?? '');
		$this->lazyPassword = (string)($state['password'] ?? '');
		$this->lazyPhone = (string)($state['phone'] ?? '');
		$this->lazyDeviceName = (string)($state['device_name'] ?? '');
		$this->lazyDeviceId = (string)($state['device_id'] ?? '');
	}

	/** @return array{ok: bool, message: string} */
	private function validateUrlReachabilityForWeb(): array {
		try {
			$this->client->get($this->lazyBaseUrl . '/devices', ['timeout' => 5]);
			return ['ok' => true, 'message' => 'API is reachable.'];
		} catch (\Exception $e) {
			$statusCode = $this->getExceptionStatusCode($e);
			if (in_array($statusCode, [401, 403], true)) {
				return ['ok' => true, 'message' => 'API is reachable and requires authentication.'];
			}

			return [
				'ok' => false,
				'message' => 'Could not reach the API endpoint: ' . $e->getMessage(),
			];
		}
	}

	/** @param array<string, mixed> $state */
	private function saveSetupState(string $sessionId, array $state): void {
		$state['base_url'] = $this->lazyBaseUrl;
		$state['username'] = $this->lazyUsername;
		$state['password'] = $this->lazyPassword;
		$state['phone'] = $this->lazyPhone;
		$state['device_name'] = $this->lazyDeviceName;
		$state['device_id'] = $this->lazyDeviceId;
		$this->interactiveSetupStateStore->save($sessionId, $state);
	}

	/**
	 * @param array<int, array<string, mixed>> $devices
	 */
	private function resolveDeviceForReuse(array $devices, string $requestedDeviceId): ?array {
		if ($devices === []) {
			return null;
		}

		if ($requestedDeviceId === '') {
			return $devices[0];
		}

		foreach ($devices as $device) {
			if ((string)($device['id'] ?? '') === $requestedDeviceId) {
				return $device;
			}
		}

		return null;
	}
}
