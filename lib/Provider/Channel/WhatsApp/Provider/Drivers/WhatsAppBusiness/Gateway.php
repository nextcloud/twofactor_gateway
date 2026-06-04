<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\PhoneNumberMask;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\FieldExposure;
use OCA\TwoFactorGateway\Provider\FieldType;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IConfigurationChangeAwareGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IDefaultInstanceAwareGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IInteractiveSetupGateway;
use OCA\TwoFactorGateway\Provider\Gateway\ITestResultEnricher;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * @method string getApiVersion()
 * @method static setApiVersion(string $apiVersion)
 * @method string getPhoneNumberId()
 * @method static setPhoneNumberId(string $phoneNumberId)
 * @method string getPhoneNumberDisplay()
 * @method static setPhoneNumberDisplay(string $phoneNumberDisplay)
 * @method string getAccessToken()
 * @method static setAccessToken(string $accessToken)
 * @method string getTemplateName()
 * @method static setTemplateName(string $templateName)
 * @method string getTemplateLanguage()
 * @method static setTemplateLanguage(string $templateLanguage)
 */
class Gateway extends AGateway implements IConfigurationChangeAwareGateway, IInteractiveSetupGateway, IDefaultInstanceAwareGateway, ITestResultEnricher {
	public function __construct(
		public IAppConfig $appConfig,
		private IClientService $clientService,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
		parent::__construct($appConfig);
	}

	#[\Override]
	public function createSettings(): Settings {
		return new Settings(
			name: 'WhatsApp Business',
			id: 'whatsappbusiness',
			allowMarkdown: true,
			fields: [
				new FieldDefinition(
					field: 'api_version',
					prompt: 'WhatsApp Graph API version:',
					default: 'v22.0',
					optional: true,
					exposure: FieldExposure::ADMIN,
				),
				new FieldDefinition(
					field: 'phone_number_id',
					prompt: 'WhatsApp Business phone number ID:',
					exposure: FieldExposure::ADMIN,
				),
				new FieldDefinition(
					field: 'phone_number_display',
					prompt: 'Phone number (for display):',
					optional: true,
					exposure: FieldExposure::ADMIN,
				),
				new FieldDefinition(
					field: 'access_token',
					prompt: 'WhatsApp Business access token:',
					type: FieldType::SECRET,
					exposure: FieldExposure::ADMIN,
				),
				new FieldDefinition(
					field: 'template_name',
					prompt: 'Template name:',
					helper: 'If set, outbound messages are sent using this approved template. For Two Factor Gateway, keep one body variable {{1}} to receive the verification token/message.',
					exposure: FieldExposure::ADMIN,
				),
				new FieldDefinition(
					field: 'template_language',
					prompt: 'Template language code:',
					helper: 'Language code used when sending the configured template, e.g. pt_BR or en_US.',
					exposure: FieldExposure::ADMIN,
				),
			],
		);
	}

	#[\Override]
	public function getProviderId(): string {
		return 'whatsappbusiness';
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		$to = preg_replace('/\D+/', '', $identifier) ?? '';
		if ($to === '') {
			throw new MessageTransmissionException($this->l10n->t('Invalid phone number for WhatsApp Business.'));
		}
		$maskedIdentifier = PhoneNumberMask::maskIdentifier($identifier);

		$apiVersion = $this->resolveApiVersion();
		$phoneNumberId = $this->getPhoneNumberId();
		$accessToken = $this->getAccessToken();
		$url = sprintf('https://graph.facebook.com/%s/%s/messages', trim($apiVersion), trim($phoneNumberId));

		$templateName = $this->resolveTemplateName($extra);
		if ($templateName === '') {
			throw new MessageTransmissionException($this->l10n->t('Template name is required for WhatsApp Business. Configure an approved template with body variable {{1}}.'));
		}
		$templateLanguage = $this->resolveTemplateLanguage($extra);
		if ($templateLanguage === '') {
			throw new MessageTransmissionException($this->l10n->t('Template language code is required for WhatsApp Business.'));
		}
		$payload = [
			'messaging_product' => 'whatsapp',
			'to' => $to,
		];

		$payload['type'] = 'template';
		$payload['template'] = [
			'name' => $templateName,
			'language' => [
				'code' => $templateLanguage,
			],
			'components' => [
				[
					'type' => 'body',
					'parameters' => [
						[
							'type' => 'text',
							'text' => $message,
						],
					],
				],
			],
		];

		try {
			$response = $this->clientService->newClient()->post($url, [
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
				],
				'json' => $payload,
			]);

			$payload = json_decode((string)$response->getBody(), true);
			if (is_array($payload) && isset($payload['error']['message'])) {
				throw new MessageTransmissionException((string)$payload['error']['message']);
			}
		} catch (MessageTransmissionException $e) {
			$this->logger->warning('WhatsApp Business send failed.', [
				'identifier' => $maskedIdentifier,
				'exception' => $e,
			]);
			throw $e;
		} catch (\Throwable $e) {
			$this->logger->warning('WhatsApp Business send failed.', [
				'identifier' => $maskedIdentifier,
				'exception' => $e,
			]);
			throw new MessageTransmissionException($this->l10n->t('Failed to send message through WhatsApp Business.'));
		}
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$helper = new QuestionHelper();
		$settings = $this->getSettings();

		foreach ($settings->fields as $field) {
			$question = new Question($field->prompt . ' ', $field->default ?? null);
			$value = trim((string)$helper->ask($input, $output, $question));
			if ($value === '' && !$field->optional) {
				$output->writeln('<error>' . $field->field . ' is required.</error>');
				return 1;
			}
			if ($value === '' && $field->optional) {
				continue;
			}

			$method = 'set' . $this->toCamel($field->field);
			$this->{$method}($value);
		}

		return 0;
	}

	#[\Override]
	public function syncAfterConfigurationChange(): void {
		// No background jobs to sync for this driver.
	}

	#[\Override]
	public function onDefaultInstanceActivated(): void {
		// No side effects required when this instance becomes default.
	}

	#[\Override]
	public function interactiveSetupStart(array $input): array {
		$sessionId = bin2hex(random_bytes(16));
		$stateKey = 'whatsappbusiness_setup_' . $sessionId;

		$this->appConfig->setValueString('twofactor_gateway', $stateKey, json_encode([
			'step' => 'bootstrap',
			'token' => '',
			'apiVersion' => '',
			'whatsAppBusinessAccountId' => '',
			'phoneNumbers' => [],
			'templates' => [],
		], JSON_THROW_ON_ERROR));

		return [
			'status' => 'ok',
			'sessionId' => $sessionId,
			'step' => 'bootstrap',
			'message' => 'Please provide your WhatsApp Business access token and API version.',
		];
	}

	#[\Override]
	public function interactiveSetupStep(string $sessionId, string $action, array $input = []): array {
		$stateKey = 'whatsappbusiness_setup_' . $sessionId;
		$stateJson = $this->appConfig->getValueString('twofactor_gateway', $stateKey, '{}');
		$state = json_decode($stateJson, true) ?? [];

		if (empty($state)) {
			return [
				'status' => 'error',
				'message' => 'Session not found or expired.',
			];
		}

		try {
			match ($action) {
				'set_credentials' => $this->handleSetCredentials($sessionId, $state, $input),
				'discover_phones' => $this->handleDiscoverPhones($sessionId, $state, $input),
				'select_phone' => $this->handleSelectPhone($sessionId, $state, $input),
				'discover_templates' => $this->handleDiscoverTemplates($sessionId, $state, $input),
				'finalize' => $this->handleFinalize($sessionId, $state, $input),
				default => throw new \InvalidArgumentException('Unknown action: ' . $action),
			};

			$updatedState = json_decode(
				$this->appConfig->getValueString('twofactor_gateway', $stateKey, '{}'),
				true
			) ?? [];

			return $this->buildStepResponse($updatedState);
		} catch (\Throwable $e) {
			$this->logger->warning('WhatsApp Business setup step failed', [
				'action' => $action,
				'exception' => $e,
			]);

			return [
				'status' => 'error',
				'message' => $e->getMessage(),
			];
		}
	}

	#[\Override]
	public function interactiveSetupCancel(string $sessionId): array {
		$stateKey = 'whatsappbusiness_setup_' . $sessionId;
		$this->appConfig->deleteKey('twofactor_gateway', $stateKey);

		return [
			'status' => 'ok',
			'message' => 'Setup cancelled.',
		];
	}

	#[\Override]
	public function enrichTestResult(array $instanceConfig, string $identifier = ''): array {
		$phoneNumberId = trim((string)($instanceConfig['phone_number_id'] ?? ''));
		if ($phoneNumberId === '') {
			return [];
		}

		return [
			'provider' => 'whatsappbusiness',
			'phone_number_id' => $phoneNumberId,
		];
	}

	private function handleSetCredentials(string $sessionId, array $state, array $input): void {
		$token = trim((string)($input['token'] ?? ''));
		$apiVersion = trim((string)($input['apiVersion'] ?? ''));
		$whatsAppBusinessAccountId = trim((string)($input['whatsAppBusinessAccountId'] ?? ''));

		if ($token === '') {
			throw new \InvalidArgumentException('Access token is required.');
		}
		if ($apiVersion === '') {
			$apiVersion = 'v22.0';
		}

		$state['token'] = $token;
		$state['apiVersion'] = $apiVersion;
		$state['whatsAppBusinessAccountId'] = $whatsAppBusinessAccountId;
		$state['step'] = 'phones_discovery';

		$this->appConfig->setValueString(
			'twofactor_gateway',
			'whatsappbusiness_setup_' . $sessionId,
			json_encode($state, JSON_THROW_ON_ERROR)
		);
	}

	private function handleDiscoverPhones(string $sessionId, array $state, array $input): void {
		if (empty($state['token'])) {
			throw new \InvalidArgumentException('Token not set. Please set credentials first.');
		}

		$whatsAppBusinessAccountId = trim((string)($state['whatsAppBusinessAccountId'] ?? ''));
		if ($whatsAppBusinessAccountId !== '') {
			$phones = $this->fetchWabaPhoneNumbers($whatsAppBusinessAccountId, $state['token'], $state['apiVersion']);
		} else {
			try {
				$phones = $this->fetchPhoneNumbers($state['token'], $state['apiVersion']);
			} catch (\Throwable $e) {
				throw new \RuntimeException('Automatic discovery could not identify your WhatsApp Business account from this token. Use a token with business asset discovery access or provide the WhatsApp Business account ID in the wizard to continue. Details: ' . $e->getMessage());
			}
		}

		$state['phoneNumbers'] = $phones;
		$state['step'] = 'phone_selection';

		$this->appConfig->setValueString(
			'twofactor_gateway',
			'whatsappbusiness_setup_' . $sessionId,
			json_encode($state, JSON_THROW_ON_ERROR)
		);
	}

	private function handleSelectPhone(string $sessionId, array $state, array $input): void {
		$phoneNumberId = trim((string)($input['phoneNumberId'] ?? ''));
		if ($phoneNumberId === '') {
			throw new \InvalidArgumentException('Phone number ID is required.');
		}

		$selectedPhone = null;
		foreach (($state['phoneNumbers'] ?? []) as $phone) {
			if ((string)($phone['id'] ?? '') === $phoneNumberId) {
				$selectedPhone = $phone;
				break;
			}
		}
		if (!is_array($selectedPhone)) {
			throw new \InvalidArgumentException('The selected phone number is not available in the current setup session.');
		}
		if (($selectedPhone['is_selectable'] ?? false) !== true) {
			$reason = trim((string)($selectedPhone['unselectable_reason'] ?? ''));
			throw new \InvalidArgumentException($reason !== '' ? $reason : 'The selected phone number is not eligible for WhatsApp Cloud API sending.');
		}

		$state['selectedPhoneNumberId'] = $phoneNumberId;
		$state['selectedPhoneNumberDisplay'] = (string)($selectedPhone['display_phone_number'] ?? '');
		$state['selectedWhatsAppBusinessAccountId'] = (string)($selectedPhone['whatsapp_business_account_id'] ?? '');
		$state['step'] = 'templates_discovery';

		$this->appConfig->setValueString(
			'twofactor_gateway',
			'whatsappbusiness_setup_' . $sessionId,
			json_encode($state, JSON_THROW_ON_ERROR)
		);
	}

	private function handleDiscoverTemplates(string $sessionId, array $state, array $input): void {
		if (empty($state['token']) || empty($state['selectedWhatsAppBusinessAccountId'])) {
			throw new \InvalidArgumentException('Token and WhatsApp Business account ID are required.');
		}

		$templates = $this->fetchTemplates(
			$state['selectedWhatsAppBusinessAccountId'],
			$state['token'],
			$state['apiVersion']
		);
		$state['templates'] = $templates;
		$state['step'] = 'template_selection';

		$this->appConfig->setValueString(
			'twofactor_gateway',
			'whatsappbusiness_setup_' . $sessionId,
			json_encode($state, JSON_THROW_ON_ERROR)
		);
	}

	private function handleFinalize(string $sessionId, array $state, array $input): void {
		$templateName = trim((string)($input['templateName'] ?? ''));
		$templateLanguage = trim((string)($input['templateLanguage'] ?? ''));

		if ($templateName === '') {
			throw new \InvalidArgumentException('Template name is required.');
		}
		if ($templateLanguage === '') {
			throw new \InvalidArgumentException('Template language is required.');
		}

		$knownTemplates = $state['templates'] ?? [];
		if (is_array($knownTemplates) && $knownTemplates !== []) {
			$selectedTemplate = null;
			foreach ($knownTemplates as $template) {
				if (!is_array($template)) {
					continue;
				}

				if (
					trim((string)($template['name'] ?? '')) === $templateName
					&& trim((string)($template['language'] ?? '')) === $templateLanguage
				) {
					$selectedTemplate = $template;
					break;
				}
			}

			if (!is_array($selectedTemplate)) {
				throw new \InvalidArgumentException('The selected template is not available in the current setup session.');
			}
			if (($selectedTemplate['is_selectable'] ?? false) !== true) {
				$reason = trim((string)($selectedTemplate['unselectable_reason'] ?? ''));
				throw new \InvalidArgumentException($reason !== '' ? $reason : 'The selected template is not approved for sending.');
			}
		}

		$state['step'] = 'complete';
		$state['result'] = [
			'phone_number_id' => $state['selectedPhoneNumberId'] ?? '',
			'phone_number_display' => $state['selectedPhoneNumberDisplay'] ?? '',
			'access_token' => $state['token'] ?? '',
			'api_version' => $state['apiVersion'] ?? 'v22.0',
			'template_name' => $templateName,
			'template_language' => $templateLanguage,
		];

		$this->appConfig->setValueString(
			'twofactor_gateway',
			'whatsappbusiness_setup_' . $sessionId,
			json_encode($state, JSON_THROW_ON_ERROR)
		);
	}

	private function fetchPhoneNumbers(string $token, string $apiVersion): array {
		try {
			$phoneNumbersById = [];

			// First try the direct assignment edge, which can work without global business listing permissions.
			foreach ($this->fetchAssignedWhatsAppBusinessAccounts($token, $apiVersion) as $assignedAccount) {
				$wabaId = trim((string)($assignedAccount['id'] ?? ''));
				if ($wabaId === '') {
					continue;
				}

				foreach ($this->fetchWabaPhoneNumbers($wabaId, $token, $apiVersion) as $phoneNumber) {
					$phoneId = trim((string)($phoneNumber['id'] ?? ''));
					if ($phoneId === '') {
						continue;
					}

					$phoneNumbersById[$phoneId] = [
						'id' => $phoneId,
						'display_phone_number' => (string)($phoneNumber['display_phone_number'] ?? ''),
						'whatsapp_business_account_id' => (string)($phoneNumber['whatsapp_business_account_id'] ?? ''),
						'code_verification_status' => (string)($phoneNumber['code_verification_status'] ?? ''),
						'platform_type' => (string)($phoneNumber['platform_type'] ?? ''),
						'is_selectable' => (bool)($phoneNumber['is_selectable'] ?? false),
						'unselectable_reason' => (string)($phoneNumber['unselectable_reason'] ?? ''),
					];
				}
			}

			// If direct assignment lookup found nothing, fall back to business-level discovery.
			if ($phoneNumbersById === []) {
				$businesses = $this->fetchBusinesses($token, $apiVersion);
				if ($businesses === []) {
					throw new \RuntimeException('The token could not list any Meta business assets.');
				}

				foreach ($businesses as $business) {
					$businessId = trim((string)($business['id'] ?? ''));
					if ($businessId === '') {
						continue;
					}

					foreach ($this->fetchBusinessPhoneNumbers($businessId, $token, $apiVersion) as $phoneNumber) {
						$phoneId = trim((string)($phoneNumber['id'] ?? ''));
						if ($phoneId === '') {
							continue;
						}

						$phoneNumbersById[$phoneId] = [
							'id' => $phoneId,
							'display_phone_number' => (string)($phoneNumber['display_phone_number'] ?? ''),
							'whatsapp_business_account_id' => (string)($phoneNumber['whatsapp_business_account_id'] ?? ''),
							'code_verification_status' => (string)($phoneNumber['code_verification_status'] ?? ''),
							'platform_type' => (string)($phoneNumber['platform_type'] ?? ''),
							'is_selectable' => (bool)($phoneNumber['is_selectable'] ?? false),
							'unselectable_reason' => (string)($phoneNumber['unselectable_reason'] ?? ''),
						];
					}
				}
			}

			if ($phoneNumbersById === []) {
				throw new \RuntimeException('No WhatsApp Business phone numbers were found for the assets visible to this token.');
			}

			return array_values($phoneNumbersById);
		} catch (\Throwable $e) {
			throw new \RuntimeException('Failed to fetch phone numbers: ' . $e->getMessage());
		}
	}

	/**
	 * @return list<array{id: string, name?: string}>
	 */
	private function fetchAssignedWhatsAppBusinessAccounts(string $token, string $apiVersion): array {
		$url = sprintf('https://graph.facebook.com/%s/me/assigned_whatsapp_business_accounts', trim($apiVersion));
		$payload = $this->graphGet($url, $token, [
			'fields' => 'id,name',
		]);

		if (!isset($payload['data']) || !is_array($payload['data'])) {
			return [];
		}

		return array_values(array_filter(
			array_map(
				static fn (array $account): array => [
					'id' => (string)($account['id'] ?? ''),
					'name' => (string)($account['name'] ?? ''),
				],
				$payload['data']
			),
			static fn (array $account): bool => $account['id'] !== ''
		));
	}

	/**
	 * @return list<array{id: string, display_phone_number?: string, whatsapp_business_account_id?: string, code_verification_status?: string, platform_type?: string, is_selectable?: bool, unselectable_reason?: string}>
	 */
	private function fetchWabaPhoneNumbers(string $whatsAppBusinessAccountId, string $token, string $apiVersion): array {
		$whatsAppBusinessAccountId = trim($whatsAppBusinessAccountId);
		if ($whatsAppBusinessAccountId === '') {
			throw new \RuntimeException('WhatsApp Business account ID is required.');
		}

		$url = sprintf(
			'https://graph.facebook.com/%s/%s/phone_numbers',
			trim($apiVersion),
			$whatsAppBusinessAccountId
		);
		$payload = $this->graphGet($url, $token);

		if (!isset($payload['data']) || !is_array($payload['data'])) {
			throw new \RuntimeException('Invalid phone number list response from Meta Graph API.');
		}

		$phones = [];
		foreach ($payload['data'] as $phoneNumber) {
			if (!is_array($phoneNumber)) {
				continue;
			}

			$phoneId = trim((string)($phoneNumber['id'] ?? ''));
			if ($phoneId === '') {
				continue;
			}

			$platformType = strtoupper(trim((string)($phoneNumber['platform_type'] ?? '')));
			$isSelectable = $platformType === 'CLOUD_API';
			$unselectableReason = $isSelectable ? '' : 'This phone number is not configured for WhatsApp Cloud API.';

			$phones[] = [
				'id' => $phoneId,
				'display_phone_number' => (string)($phoneNumber['display_phone_number'] ?? ''),
				'whatsapp_business_account_id' => $whatsAppBusinessAccountId,
				'code_verification_status' => (string)($phoneNumber['code_verification_status'] ?? ''),
				'platform_type' => (string)($phoneNumber['platform_type'] ?? ''),
				'is_selectable' => $isSelectable,
				'unselectable_reason' => $unselectableReason,
			];
		}

		if ($phones === []) {
			throw new \RuntimeException('No phone numbers were returned for the provided WhatsApp Business account ID.');
		}

		return $phones;
	}

	/**
	 * @return list<array{id: string, name?: string}>
	 */
	private function fetchBusinesses(string $token, string $apiVersion): array {
		$url = sprintf('https://graph.facebook.com/%s/me/businesses', trim($apiVersion));
		$payload = $this->graphGet($url, $token, [
			'fields' => 'id,name',
		]);

		if (!isset($payload['data']) || !is_array($payload['data'])) {
			throw new \RuntimeException('Invalid business list response from Meta Graph API.');
		}

		return array_values(array_filter(
			array_map(
				static fn (array $business): array => [
					'id' => (string)($business['id'] ?? ''),
					'name' => (string)($business['name'] ?? ''),
				],
				$payload['data']
			),
			static fn (array $business): bool => $business['id'] !== ''
		));
	}

	/**
	 * @return list<array{id: string, display_phone_number?: string, whatsapp_business_account_id?: string, code_verification_status?: string, platform_type?: string, is_selectable?: bool, unselectable_reason?: string}>
	 */
	private function fetchBusinessPhoneNumbers(string $businessId, string $token, string $apiVersion): array {
		$url = sprintf(
			'https://graph.facebook.com/%s/%s/owned_whatsapp_business_accounts',
			trim($apiVersion),
			trim($businessId)
		);
		$payload = $this->graphGet($url, $token, [
			'fields' => 'id,name,phone_numbers{id,display_phone_number,code_verification_status,platform_type}',
		]);

		if (!isset($payload['data']) || !is_array($payload['data'])) {
			throw new \RuntimeException('Invalid WhatsApp Business account list response from Meta Graph API.');
		}

		$phoneNumbers = [];
		foreach ($payload['data'] as $account) {
			$whatsAppBusinessAccountId = (string)($account['id'] ?? '');
			$nestedPhoneNumbers = $account['phone_numbers']['data'] ?? $account['phone_numbers'] ?? [];
			if (!is_array($nestedPhoneNumbers)) {
				continue;
			}

			foreach ($nestedPhoneNumbers as $phoneNumber) {
				if (!is_array($phoneNumber)) {
					continue;
				}

				$platformType = strtoupper(trim((string)($phoneNumber['platform_type'] ?? '')));
				$isSelectable = $platformType === 'CLOUD_API';
				$unselectableReason = $isSelectable ? '' : 'This phone number is not configured for WhatsApp Cloud API.';

				$phoneNumbers[] = [
					'id' => (string)($phoneNumber['id'] ?? ''),
					'display_phone_number' => (string)($phoneNumber['display_phone_number'] ?? ''),
					'whatsapp_business_account_id' => $whatsAppBusinessAccountId,
					'code_verification_status' => (string)($phoneNumber['code_verification_status'] ?? ''),
					'platform_type' => (string)($phoneNumber['platform_type'] ?? ''),
					'is_selectable' => $isSelectable,
					'unselectable_reason' => $unselectableReason,
				];
			}
		}

		return $phoneNumbers;
	}

	/**
	 * @param array<string, string> $query
	 * @return array<string, mixed>
	 */
	private function graphGet(string $url, string $token, array $query = []): array {
		try {
			$response = $this->clientService->newClient()->get($url, [
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
				],
				'query' => $query,
			]);
		} catch (\Throwable $e) {
			throw new \RuntimeException($this->extractGraphErrorMessage($e));
		}

		$payload = json_decode((string)$response->getBody(), true);
		if (!is_array($payload)) {
			throw new \RuntimeException('Invalid response from Meta Graph API.');
		}

		return $payload;
	}

	private function extractGraphErrorMessage(\Throwable $e): string {
		$default = 'Meta Graph API request failed.';

		if (method_exists($e, 'getResponse')) {
			/** @var object|null $response */
			$response = $e->getResponse();
			if ($response !== null && method_exists($response, 'getBody')) {
				$rawBody = (string)$response->getBody();
				$decoded = json_decode($rawBody, true);
				if (is_array($decoded) && isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
					return 'Meta Graph API error: ' . $decoded['error']['message'];
				}
			}
		}

		if ($e->getMessage() !== '') {
			return $e->getMessage();
		}

		return $default;
	}

	private function fetchTemplates(string $whatsAppBusinessAccountId, string $token, string $apiVersion): array {
		try {
			$url = sprintf(
				'https://graph.facebook.com/%s/%s/message_templates?fields=name,language,status,components',
				trim($apiVersion),
				trim($whatsAppBusinessAccountId)
			);
			$payload = $this->graphGet($url, $token);
			if (!is_array($payload) || !isset($payload['data'])) {
				throw new \RuntimeException('Invalid response from Meta Graph API.');
			}

			return array_values(array_map(
				static function (array $template): array {
					$status = strtoupper(trim((string)($template['status'] ?? '')));

					// Extract template body, header, and footer from components
					$body = '';
					$header = '';
					$footer = '';
					if (isset($template['components']) && is_array($template['components'])) {
						foreach ($template['components'] as $component) {
							$type = strtoupper(trim((string)($component['type'] ?? '')));
							if ($type === 'BODY' && isset($component['text'])) {
								$body = (string)$component['text'];
							} elseif ($type === 'HEADER' && isset($component['text'])) {
								$header = (string)$component['text'];
							} elseif ($type === 'FOOTER' && isset($component['text'])) {
								$footer = (string)$component['text'];
							}
						}
					}

					return [
						'name' => (string)($template['name'] ?? ''),
						'language' => (string)($template['language'] ?? ''),
						'status' => $status,
						'body' => $body,
						'header' => $header,
						'footer' => $footer,
						'is_selectable' => $status === 'APPROVED',
						'unselectable_reason' => $status === 'APPROVED' ? '' : 'Template is not approved.',
					];
				},
				array_filter(
					$payload['data'],
					static fn (array $template): bool => trim((string)($template['name'] ?? '')) !== '' && trim((string)($template['language'] ?? '')) !== ''
				)
			));
		} catch (\Throwable $e) {
			throw new \RuntimeException('Failed to fetch templates: ' . $e->getMessage());
		}
	}

	private function buildStepResponse(array $state): array {
		$response = [
			'status' => 'ok',
			'step' => $state['step'] ?? 'complete',
		];

		if ($state['step'] === 'phones_discovery') {
			$response['message'] = 'Fetching available phone numbers...';
		} elseif ($state['step'] === 'phone_selection') {
			$response['phoneNumbers'] = $state['phoneNumbers'] ?? [];
			$response['message'] = 'Select a phone number to continue.';
		} elseif ($state['step'] === 'templates_discovery') {
			$response['message'] = 'Fetching available templates...';
		} elseif ($state['step'] === 'template_selection') {
			$response['templates'] = $state['templates'] ?? [];
			$response['message'] = 'Select a template and language to continue.';
		} elseif ($state['step'] === 'complete') {
			$response['message'] = 'Setup completed successfully!';
			$response['result'] = $state['result'] ?? [];
		}

		return $response;
	}

	private function resolveApiVersion(): string {
		try {
			$apiVersion = trim($this->getApiVersion());
			if ($apiVersion !== '') {
				return $apiVersion;
			}
		} catch (\Throwable) {
			// Fallback to the settings default when the optional field is not configured.
		}

		foreach ($this->getSettings()->fields as $field) {
			if ($field->field === 'api_version') {
				return trim((string)($field->default ?? 'v22.0'));
			}
		}

		return 'v22.0';
	}

	private function resolveTemplateName(array $extra): string {
		$runtimeTemplateName = trim((string)($extra['template_name'] ?? ''));
		if ($runtimeTemplateName !== '') {
			return $runtimeTemplateName;
		}

		try {
			return trim($this->getTemplateName());
		} catch (\Throwable) {
			return '';
		}
	}

	private function resolveTemplateLanguage(array $extra): string {
		$runtimeTemplateLanguage = trim((string)($extra['template_language'] ?? ''));
		if ($runtimeTemplateLanguage !== '') {
			return $runtimeTemplateLanguage;
		}

		try {
			return trim($this->getTemplateLanguage());
		} catch (\Throwable) {
			return '';
		}
	}
}
