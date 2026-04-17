<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp;

use OCA\TwoFactorGateway\Events\WhatsAppAuthenticationErrorEvent;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

trait GatewayCliSetupTrait {
	private function writeCliFeedback(OutputInterface $output, string $type, string $message): void {
		$tag = match ($type) {
			'success', 'info' => 'info',
			'warning' => 'comment',
			'error' => 'error',
			default => 'comment',
		};

		$output->writeln('<' . $tag . '>' . $message . '</' . $tag . '>');
	}

	private function displayDeviceList(OutputInterface $output, array $devices): void {
		foreach ($devices as $index => $device) {
			$deviceId = $device['id'] ?? 'Unknown';
			$displayName = $device['display_name'] ?? '';
			$phoneNumber = $device['phone_number'] ?? '';
			$state = $device['state'] ?? 'unknown';
			$createdAt = $device['created_at'] ?? '';

			if (in_array($state, ['connected', 'logged_in'], true)) {
				$stateFormatted = '<info>' . $state . '</info>';
			} else {
				$stateFormatted = '<error>' . $state . '</error>';
			}

			$createdFormatted = '';
			if (!empty($createdAt)) {
				try {
					$date = new \DateTime($createdAt);
					$createdFormatted = ' - Created: ' . $date->format('Y-m-d H:i:s');
				} catch (\Exception) {
				}
			}

			$output->writeln('  ' . ($index + 1) . '. <comment>' . ($displayName ?: $phoneNumber ?: 'Unknown') . '</comment>');
			$output->writeln('     ID: ' . $deviceId . ' [' . $stateFormatted . ']' . $createdFormatted);
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
			$response = $this->client->get($this->getBaseUrl() . '/devices', [
				'timeout' => 5,
				'auth' => $this->getBasicAuth(),
			]);

			$body = (string)$response->getBody();
			$data = json_decode($body, true);

			if (($data['code'] ?? '') === 'SUCCESS' && isset($data['results'])) {
				return $data['results'];
			}

			return [];
		} catch (\Exception $e) {
			$statusCode = $this->getExceptionStatusCode($e);
			if ($statusCode === 404) {
				return null;
			}

			return [];
		}
	}

	private function createNewDevice(OutputInterface $output): bool {
		try {
			$response = $this->client->post($this->getBaseUrl() . '/devices', [
				'timeout' => 5,
				'auth' => $this->getBasicAuth(),
				'json' => [
					'device_id' => $this->getDeviceNameValue(),
				],
			]);

			$body = (string)$response->getBody();
			$data = json_decode($body, true);

			$this->logger->debug('Create device response', ['data' => $data]);

			if (($data['code'] ?? '') === 'SUCCESS' || ($data['code'] ?? '') === 'CREATED') {
				$deviceId = $data['results']['id'];
				if (!empty($deviceId)) {
					$this->lazyDeviceId = $deviceId;
					$this->logger->info('Captured device_id', ['device_id' => $deviceId]);
				} else {
					$this->logger->warning('No device_id found in create device response');
				}
				return true;
			}

			$output->writeln('<error>✗ Failed to create device: ' . ($data['message'] ?? 'Unknown error') . '</error>');
			return false;
		} catch (\Exception $e) {
			$output->writeln('<error>✗ Error creating device: ' . $e->getMessage() . '</error>');
			$this->logger->error('Failed to create device', ['exception' => $e]);
			return false;
		}
	}

	private function requestPairingCode(InputInterface $input, OutputInterface $output): array {
		$this->writeCliFeedback($output, 'info', 'Requesting pairing code...');

		$result = $this->fetchPairingCode();

		if (!$result['success'] && !$result['alreadyLoggedIn']) {
			$this->writeCliFeedback($output, 'error', 'Could not connect to the WhatsApp API. Please check the URL.');
			return ['status' => self::CONFIG_ERROR, 'code' => null];
		}

		if ($result['alreadyLoggedIn']) {
			$configResult = $this->handleAlreadyLoggedIn($input, $output);
			if ($configResult === self::CONFIG_SUCCESS) {
				return ['status' => self::CONFIG_SUCCESS, 'code' => null];
			}

			if ($configResult === self::CONFIG_ERROR) {
				return ['status' => self::CONFIG_ERROR, 'code' => null];
			}

			$result = $this->fetchPairingCode();
			if (!$result['success']) {
				$this->writeCliFeedback($output, 'error', 'Could not get pairing code after logout.');
				return ['status' => self::CONFIG_ERROR, 'code' => null];
			}
		}

		return ['status' => self::CONFIG_CONTINUE, 'code' => $result['code']];
	}

	private function fetchPairingCode(): array {
		try {
			$this->logger->debug('Fetching pairing code', [
				'phone' => $this->lazyPhone,
				'device_id' => $this->lazyDeviceId,
			]);

			$options = [
				'query' => ['phone' => $this->lazyPhone],
				'auth' => $this->getBasicAuth(),
			];

			$deviceId = $this->getDeviceId();
			if (!empty($deviceId)) {
				$options['headers'] = ['X-Device-Id' => $deviceId];
			}

			$response = $this->client->get($this->getBaseUrl() . '/app/login-with-code', $options);

			$body = (string)$response->getBody();
			$data = json_decode($body, true);

			if (($data['code'] ?? '') !== 'SUCCESS') {
				$apiMessage = (string)($data['message'] ?? ($data['error'] ?? ''));
				return ['success' => false, 'code' => null, 'alreadyLoggedIn' => false, 'errorMessage' => $apiMessage];
			}

			$pairCode = $data['results']['pair_code'] ?? '';
			if (empty($pairCode)) {
				$apiMessage = (string)($data['message'] ?? ($data['error'] ?? ''));
				return ['success' => false, 'code' => null, 'alreadyLoggedIn' => false, 'errorMessage' => $apiMessage];
			}

			return ['success' => true, 'code' => $pairCode, 'alreadyLoggedIn' => false, 'errorMessage' => ''];
		} catch (\Exception $e) {
			$statusCode = $this->getExceptionStatusCode($e);
			$body = $this->getExceptionResponseBody($e);
			$data = is_string($body) ? json_decode($body, true) : null;

			if (is_array($data) && ($data['code'] ?? '') === 'ALREADY_LOGGED_IN') {
				return ['success' => false, 'code' => null, 'alreadyLoggedIn' => true, 'errorMessage' => ''];
			}

			$apiMessage = is_array($data) ? (string)($data['message'] ?? ($data['error'] ?? '')) : '';
			$this->logger->error('API connection error', ['exception' => $e, 'status_code' => $statusCode]);
			return ['success' => false, 'code' => null, 'alreadyLoggedIn' => false, 'errorMessage' => $apiMessage];
		}
	}

	private function handleAlreadyLoggedIn(InputInterface $input, OutputInterface $output): int {
		$this->writeCliFeedback($output, 'info', 'Account is already logged in.');

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
			$this->setDeviceName($this->getDeviceNameValue());
			$this->setDeviceId($this->lazyDeviceId);
			$this->writeCliFeedback($output, 'success', 'Configuration saved successfully.');
			return self::CONFIG_SUCCESS;
		}

		$logoutSuccess = $this->performLogout($output);
		return $logoutSuccess ? self::CONFIG_CONTINUE : self::CONFIG_ERROR;
	}

	private function getFieldPrompt(string $fieldName): string {
		foreach ($this->getSettings()->fields as $field) {
			if ($field->field === $fieldName) {
				return $field->prompt;
			}
		}
		return $fieldName . ':';
	}

	private function getFieldDefault(string $fieldName): string {
		foreach ($this->getSettings()->fields as $field) {
			if ($field->field === $fieldName) {
				return $field->default;
			}
		}
		return '';
	}

	private function getDeviceNameValue(): string {
		if ($this->lazyDeviceName !== '') {
			return $this->lazyDeviceName;
		}

		try {
			$this->lazyDeviceName = $this->getDeviceName();
			return $this->lazyDeviceName;
		} catch (\Exception) {
		}

		$this->lazyDeviceName = $this->getFieldDefault('device_name') ?: 'TwoFactor Gateway';
		return $this->lazyDeviceName;
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
		$this->writeCliFeedback($output, 'info', 'Logging out device...');
		try {
			$options = [
				'auth' => $this->getBasicAuth(),
			];

			$deviceId = $this->getDeviceId();
			if (!empty($deviceId)) {
				$options['headers'] = ['X-Device-Id' => $deviceId];
			}

			$this->client->get($this->getBaseUrl() . '/app/logout', $options);
			$this->writeCliFeedback($output, 'success', 'Device logged out successfully. Please set up a new device.');
			return true;
		} catch (\Exception $e) {
			$this->writeCliFeedback($output, 'error', 'Could not logout device. Please try manually.');
			$this->logger->error('Logout failed', ['exception' => $e]);
			return false;
		}
	}

	private function validateUrlReachability(OutputInterface $output): bool {
		try {
			$this->client->get($this->lazyBaseUrl . '/devices', [
				'timeout' => 5,
			]);

			$output->writeln('<info>✓ API is reachable.</info>');
			return true;
		} catch (\Exception $e) {
			$statusCode = $this->getExceptionStatusCode($e);
			if (in_array($statusCode, [401, 403], true)) {
				$output->writeln('<info>✓ API is reachable (authentication required).</info>');
				return true;
			}
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
				'status' => $statusCode,
				'error' => $errorMessage,
				'exception' => $e,
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
			$options = [
				'query' => ['phone' => $phone],
				'auth' => $this->getBasicAuth(),
			];

			$deviceId = $this->getDeviceId();
			if (!empty($deviceId)) {
				$options['headers'] = ['X-Device-Id' => $deviceId];
			}

			$response = $this->client->get($this->getBaseUrl() . '/user/check', $options);

			$body = (string)$response->getBody();
			$data = json_decode($body, true);

			return ($data['code'] ?? '') === 'SUCCESS'
				&& ($data['results']['is_on_whatsapp'] ?? false) === true;
		} catch (\Exception $e) {
			$status = $this->getExceptionStatusCode($e);
			$body = $this->getExceptionResponseBody($e);
			$data = json_decode($body, true);

			if ($status === 401 || ($data['code'] ?? '') === 'AUTHENTICATION_ERROR') {
				$this->eventDispatcher->dispatchTyped(new WhatsAppAuthenticationErrorEvent());

				throw new MessageTransmissionException(
					$this->l10n->t('Authentication failed with WhatsApp API. Please verify username/password or log in again.'),
					self::CODE_AUTHENTICATION,
				);
			}

			if ($status === 404 || ($data['code'] ?? '') === 'DEVICE_NOT_FOUND') {
				$this->eventDispatcher->dispatchTyped(new WhatsAppAuthenticationErrorEvent());

				throw new MessageTransmissionException(
					$this->l10n->t('WhatsApp device not found or not connected. Please reconfigure the device.'),
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

	private function getExceptionStatusCode(\Exception $exception): ?int {
		if (!method_exists($exception, 'getResponse')) {
			return null;
		}

		$response = $exception->getResponse();
		if (!is_object($response) || !method_exists($response, 'getStatusCode')) {
			return null;
		}

		$statusCode = $response->getStatusCode();
		return is_int($statusCode) ? $statusCode : null;
	}

	private function getExceptionResponseBody(\Exception $exception): string {
		if (!method_exists($exception, 'getResponse')) {
			return '';
		}

		$response = $exception->getResponse();
		if (!is_object($response) || !method_exists($response, 'getBody')) {
			return '';
		}

		$body = $response->getBody();
		return is_object($body) && method_exists($body, '__toString') ? (string)$body : '';
	}

	private function getBasicAuth(): array {
		try {
			$username = $this->lazyUsername ?: $this->getUsername();
			$password = $this->lazyPassword ?: $this->getPassword();
			return [$username, $password];
		} catch (\Exception) {
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

	private function getDeviceId(): string {
		if ($this->lazyDeviceId !== '') {
			return $this->lazyDeviceId;
		}
		try {
			/** @var string */
			$this->lazyDeviceId = parent::__call('getDeviceId', []);
		} catch (\Exception) {
			$this->lazyDeviceId = '';
		}
		return $this->lazyDeviceId;
	}

	private function askDeviceChoiceAction(InputInterface $input, OutputInterface $output, QuestionHelper $helper): int {
		$actions = [
			'Use an existing device (logout others)',
			'Logout all devices and create new one',
			'Create a new device (keep existing ones)',
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

	private function logoutDevice(OutputInterface $output, array $device): bool {
		try {
			$deviceId = $device['id'] ?? '';
			if (empty($deviceId)) {
				return false;
			}

			$currentDeviceId = $this->lazyDeviceId;
			$this->lazyDeviceId = $deviceId;

			$this->performLogout($output);

			$this->lazyDeviceId = $currentDeviceId;

			return true;
		} catch (\Exception $e) {
			$this->logger->error('Failed to logout device', [
				'device_id' => $device['id'] ?? '',
				'exception' => $e,
			]);
			return false;
		}
	}

	private function logoutAllDevices(OutputInterface $output, array $devices): void {
		foreach ($devices as $device) {
			$deviceId = $device['id'] ?? '';
			$displayName = $device['display_name'] ?? $deviceId;

			if (!empty($deviceId)) {
				$output->writeln('  Logging out: ' . $displayName);
				$this->logoutDevice($output, $device);
			}
		}
	}
}
