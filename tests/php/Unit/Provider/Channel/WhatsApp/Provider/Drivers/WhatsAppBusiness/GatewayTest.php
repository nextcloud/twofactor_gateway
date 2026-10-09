<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\PhoneNumberMask;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness\Gateway;
use OCA\TwoFactorGateway\Provider\FieldExposure;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

class GatewayTest extends AppTestCase {
	private IClient&MockObject $client;
	private LoggerInterface&MockObject $logger;
	private Gateway $gateway;

	protected function setUp(): void {
		parent::setUp();

		$appConfig = $this->makeInMemoryAppConfig();
		$clientService = $this->createMock(IClientService::class);
		$this->client = $this->createMock(IClient::class);
		$clientService->method('newClient')->willReturn($this->client);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

		$this->logger = $this->createMock(LoggerInterface::class);

		$this->gateway = new Gateway(
			appConfig: $appConfig,
			clientService: $clientService,
			l10n: $l10n,
			logger: $this->logger,
		);
	}

	public function testCreateSettingsMarksAllFieldsAsDelegated(): void {
		$settings = $this->gateway->createSettings();
		$fieldByName = [];
		foreach ($settings->fields as $field) {
			$fieldByName[$field->field] = $field;
		}

		$this->assertSame(FieldExposure::DELEGATED->value, $fieldByName['api_version']->getExposure());
		$this->assertSame(FieldExposure::DELEGATED->value, $fieldByName['phone_number_id']->getExposure());
		$this->assertSame(FieldExposure::DELEGATED->value, $fieldByName['phone_number_display']->getExposure());
		$this->assertSame(FieldExposure::DELEGATED->value, $fieldByName['access_token']->getExposure());
		$this->assertSame(FieldExposure::DELEGATED->value, $fieldByName['template_name']->getExposure());
		$this->assertSame(FieldExposure::DELEGATED->value, $fieldByName['template_language']->getExposure());
		$this->assertSame(FieldExposure::DELEGATED->value, $fieldByName['template_category']->getExposure());
	}

	public function testSendUsesTemplateAndDefaultApiVersionWhenNotConfigured(): void {
		$this->gateway->setPhoneNumberId('test_9999999999999');
		$this->gateway->setAccessToken('token-123');
		$this->gateway->setTemplateName('test_twofactor_token');
		$this->gateway->setTemplateLanguage('pt_BR');

		$this->client->expects($this->once())
			->method('post')
			->with(
				'https://graph.facebook.com/v22.0/test_9999999999999/messages',
				$this->callback(static function (array $options): bool {
					$payload = $options['json'] ?? [];
					$parameters = $payload['template']['components'][0]['parameters'][0] ?? [];

					return ($options['headers']['Authorization'] ?? null) === 'Bearer token-123'
						&& ($payload['to'] ?? null) === '5511999990000'
						&& ($payload['type'] ?? null) === 'template'
						&& ($payload['template']['name'] ?? null) === 'test_twofactor_token'
						&& ($payload['template']['language']['code'] ?? null) === 'pt_BR'
						&& ($parameters['text'] ?? null) === 'Two Factor Gateway test message';
				}),
			)
			->willReturn($this->createJsonResponse(['messages' => [['id' => 'wamid.1']]]));

		$this->gateway->send('+55 (11) 99999-0000', 'Two Factor Gateway test message');
	}

	public function testSendThrowsWhenTemplateNameIsMissing(): void {
		$this->gateway->setPhoneNumberId('test_9999999999999');
		$this->gateway->setAccessToken('token-123');

		$this->client->expects($this->never())->method('post');

		$this->expectException(MessageTransmissionException::class);
		$this->expectExceptionMessage('Template name is required for WhatsApp Business. Configure an approved template with body variable {{1}}.');

		$this->gateway->send('+55 (11) 99999-0000', 'Two Factor Gateway test message');
	}

	public function testSendThrowsWhenTemplateLanguageIsMissing(): void {
		$this->gateway->setPhoneNumberId('test_9999999999999');
		$this->gateway->setAccessToken('token-123');
		$this->gateway->setTemplateName('test_twofactor_token');

		$this->client->expects($this->never())->method('post');

		$this->expectException(MessageTransmissionException::class);
		$this->expectExceptionMessage('Template language code is required for WhatsApp Business.');

		$this->gateway->send('+55 (11) 99999-0000', 'Two Factor Gateway test message');
	}

	public function testSendThrowsWhenIdentifierIsInvalid(): void {
		$this->expectException(MessageTransmissionException::class);
		$this->expectExceptionMessage('Invalid phone number for WhatsApp Business.');

		$this->gateway->send('not-a-phone', 'Two Factor Gateway test message');
	}

	public function testSendThrowsProviderErrorMessageWhenGraphReturnsErrorPayload(): void {
		$this->gateway->setApiVersion('v25.0');
		$this->gateway->setPhoneNumberId('test_9999999999999');
		$this->gateway->setAccessToken('token-123');
		$this->gateway->setTemplateName('test_twofactor_token');
		$this->gateway->setTemplateLanguage('pt_BR');
		$maskedIdentifier = PhoneNumberMask::maskIdentifier('+55 (11) 99999-0000');

		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'WhatsApp Business send failed.',
				$this->callback(static function (array $context) use ($maskedIdentifier): bool {
					return ($context['identifier'] ?? null) === $maskedIdentifier
						&& ($context['errorClass'] ?? null) === MessageTransmissionException::class;
				}),
			);

		$this->client->expects($this->once())
			->method('post')
			->willReturn($this->createJsonResponse([
				'error' => [
					'message' => 'Unsupported post request',
				],
			]));

		$this->expectException(MessageTransmissionException::class);
		$this->expectExceptionMessage('Meta rejected the WhatsApp template message.');

		$this->gateway->send('+55 (11) 99999-0000', 'Two Factor Gateway test message');
	}

	public function testSendWrapsUnexpectedClientException(): void {
		$this->gateway->setApiVersion('v25.0');
		$this->gateway->setPhoneNumberId('test_9999999999999');
		$this->gateway->setAccessToken('token-123');
		$this->gateway->setTemplateName('test_twofactor_token');
		$this->gateway->setTemplateLanguage('pt_BR');
		$maskedIdentifier = PhoneNumberMask::maskIdentifier('+55 (11) 99999-0000');

		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'WhatsApp Business send failed.',
				$this->callback(static function (array $context) use ($maskedIdentifier): bool {
					return ($context['identifier'] ?? null) === $maskedIdentifier
						&& ($context['errorClass'] ?? null) === \RuntimeException::class;
				}),
			);

		$this->client->expects($this->once())
			->method('post')
			->willThrowException(new \RuntimeException('Network down'));

		$this->expectException(MessageTransmissionException::class);
		$this->expectExceptionMessage('Failed to send message through WhatsApp Business.');

		$this->gateway->send('+55 (11) 99999-0000', 'Two Factor Gateway test message');
	}

	public function testSendUsesConfiguredTemplateWhenTemplateNameIsConfigured(): void {
		$this->gateway->setApiVersion('v25.0');
		$this->gateway->setPhoneNumberId('test_9999999999999');
		$this->gateway->setAccessToken('token-123');
		$this->gateway->setTemplateName('test_twofactor_verification');
		$this->gateway->setTemplateLanguage('pt_BR');

		$this->client->expects($this->once())
			->method('post')
			->with(
				'https://graph.facebook.com/v25.0/test_9999999999999/messages',
				$this->callback(static function (array $options): bool {
					$payload = $options['json'] ?? [];
					$parameters = $payload['template']['components'][0]['parameters'][0] ?? [];

					return ($payload['type'] ?? null) === 'template'
						&& ($payload['template']['name'] ?? null) === 'test_twofactor_verification'
						&& ($payload['template']['language']['code'] ?? null) === 'pt_BR'
						&& ($parameters['type'] ?? null) === 'text'
						&& ($parameters['text'] ?? null) === 'Your verification code is 123456.';
				}),
			)
			->willReturn($this->createJsonResponse(['messages' => [['id' => 'wamid.2']]]));

		$this->gateway->send('+55 (11) 99999-0000', 'Your verification code is 123456.');
	}

	public function testSendUsesRuntimeTemplateOverridesFromExtra(): void {
		$this->gateway->setApiVersion('v25.0');
		$this->gateway->setPhoneNumberId('test_9999999999999');
		$this->gateway->setAccessToken('token-123');

		$this->client->expects($this->once())
			->method('post')
			->with(
				'https://graph.facebook.com/v25.0/test_9999999999999/messages',
				$this->callback(static function (array $options): bool {
					$payload = $options['json'] ?? [];
					$parameters = $payload['template']['components'][0]['parameters'][0] ?? [];

					return ($payload['type'] ?? null) === 'template'
						&& ($payload['template']['name'] ?? null) === 'test_twofactor_custom_message'
						&& ($payload['template']['language']['code'] ?? null) === 'pt_BR'
						&& ($parameters['text'] ?? null) === 'Two Factor Gateway custom test message.';
				}),
			)
			->willReturn($this->createJsonResponse(['messages' => [['id' => 'wamid.3']]]));

		$this->gateway->send(
			'+55 (11) 99999-0000',
			'Two Factor Gateway custom test message.',
			[
				'template_name' => 'test_twofactor_custom_message',
				'template_language' => 'pt_BR',
			],
		);
	}

	public function testAuthenticationTemplateUsesConfiguredNameAndOtpButton(): void {
		$this->gateway->setPhoneNumberId('test_999');
		$this->gateway->setAccessToken('private-token');
		$this->gateway->setTemplateName('user_configured_otp');
		$this->gateway->setTemplateLanguage('pt_BR');
		$this->gateway->setTemplateCategory('AUTHENTICATION');
		$this->client->expects($this->once())->method('post')->with(
			'https://graph.facebook.com/v22.0/test_999/messages',
			$this->callback(static function (array $options): bool {
				$template = $options['json']['template'];
				return $template['name'] === 'user_configured_otp'
					&& $template['components'][0]['parameters'][0]['text'] === '482931'
					&& $template['components'][1]['sub_type'] === 'url'
					&& $template['components'][1]['index'] === '0'
					&& $template['components'][1]['parameters'][0]['text'] === '482931';
			}),
		)->willReturn($this->createJsonResponse(['messages' => [['id' => 'wamid.test']]]));
		$this->gateway->send('+5511999990000', 'Explanatory login message', ['code' => '482931']);
	}

	public function testAuthenticationTemplateRejectsNonOtpMessages(): void {
		$this->gateway->setPhoneNumberId('test_999');
		$this->gateway->setAccessToken('private-token');
		$this->gateway->setTemplateName('user_configured_otp');
		$this->gateway->setTemplateLanguage('pt_BR');
		$this->gateway->setTemplateCategory('AUTHENTICATION');
		$this->client->expects($this->never())->method('post');
		$this->expectException(MessageTransmissionException::class);
		$this->gateway->send('+5511999990000', 'Two Factor Gateway test message');
	}

	public function testAdministrativeTestMessageGeneratesSixDigitCode(): void {
		$payload = $this->gateway->createTestMessage();
		$this->assertMatchesRegularExpression('/^[0-9]{6}$/', $payload['message']);
		$this->assertSame(['code' => $payload['message']], $payload['extra']);
		$this->assertArrayNotHasKey('template_name', $payload['extra']);
	}

	public function testClientExceptionDoesNotLogBearerToken(): void {
		$this->gateway->setPhoneNumberId('test_999');
		$this->gateway->setAccessToken('sensitive-token-do-not-log');
		$this->gateway->setTemplateName('configured_template');
		$this->gateway->setTemplateLanguage('pt_BR');
		$this->client->method('post')->willThrowException(new \RuntimeException('Bearer sensitive-token-do-not-log'));
		$this->logger->expects($this->once())->method('warning')->with(
			'WhatsApp Business send failed.',
			$this->callback(static function (array $context): bool {
				return !str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'sensitive-token-do-not-log')
					&& !isset($context['exception']);
			}),
		);
		$this->expectException(MessageTransmissionException::class);
		$this->gateway->send('+5511999990000', 'test');
	}

	private function createJsonResponse(array $payload): IResponse {
		$stream = $this->createStub(StreamInterface::class);
		$stream->method('__toString')->willReturn((string)json_encode($payload));

		$response = $this->createStub(IResponse::class);
		$response->method('getBody')->willReturn($stream);

		return $response;
	}
}
