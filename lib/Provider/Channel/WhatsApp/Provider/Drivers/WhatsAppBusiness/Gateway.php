<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
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
 * @method string getAccessToken()
 * @method static setAccessToken(string $accessToken)
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
				),
				new FieldDefinition(
					field: 'phone_number_id',
					prompt: 'WhatsApp Business phone number ID:',
				),
				new FieldDefinition(
					field: 'access_token',
					prompt: 'WhatsApp Business access token:',
					type: FieldType::SECRET,
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

		$apiVersion = $this->getApiVersion();
		$phoneNumberId = $this->getPhoneNumberId();
		$accessToken = $this->getAccessToken();
		$url = sprintf('https://graph.facebook.com/%s/%s/messages', trim($apiVersion), trim($phoneNumberId));

		try {
			$response = $this->clientService->newClient()->post($url, [
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
				],
				'json' => [
					'messaging_product' => 'whatsapp',
					'to' => $to,
					'type' => 'text',
					'text' => [
						'body' => $message,
						'preview_url' => false,
					],
				],
			]);

			$payload = json_decode((string)$response->getBody(), true);
			if (is_array($payload) && isset($payload['error']['message'])) {
				throw new MessageTransmissionException((string)$payload['error']['message']);
			}
		} catch (MessageTransmissionException $e) {
			$this->logger->warning('WhatsApp Business send failed.', [
				'identifier' => $identifier,
				'exception' => $e,
			]);
			throw $e;
		} catch (\Throwable $e) {
			$this->logger->warning('WhatsApp Business send failed.', [
				'identifier' => $identifier,
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
		return [
			'status' => 'error',
			'message' => 'Interactive setup is not supported for WhatsApp Business yet.',
		];
	}

	#[\Override]
	public function interactiveSetupStep(string $sessionId, string $action, array $input = []): array {
		return [
			'status' => 'error',
			'message' => 'Interactive setup is not supported for WhatsApp Business yet.',
		];
	}

	#[\Override]
	public function interactiveSetupCancel(string $sessionId): array {
		return [
			'status' => 'ok',
			'message' => 'Nothing to cancel for WhatsApp Business interactive setup.',
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
}
