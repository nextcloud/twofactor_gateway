<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness\Gateway;
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
	private Gateway $gateway;

	protected function setUp(): void {
		parent::setUp();

		$appConfig = $this->makeInMemoryAppConfig();
		$clientService = $this->createMock(IClientService::class);
		$this->client = $this->createMock(IClient::class);
		$clientService->method('newClient')->willReturn($this->client);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

		$logger = $this->createMock(LoggerInterface::class);

		$this->gateway = new Gateway(
			appConfig: $appConfig,
			clientService: $clientService,
			l10n: $l10n,
			logger: $logger,
		);
	}

	public function testSendFallsBackToDefaultApiVersionWhenNotConfigured(): void {
		$this->gateway->setPhoneNumberId('1068309859700859');
		$this->gateway->setAccessToken('token-123');

		$this->client->expects($this->once())
			->method('post')
			->with(
				'https://graph.facebook.com/v22.0/1068309859700859/messages',
				$this->callback(static function (array $options): bool {
					return ($options['headers']['Authorization'] ?? null) === 'Bearer token-123'
						&& ($options['json']['to'] ?? null) === '5521993408474'
						&& ($options['json']['type'] ?? null) === 'text'
						&& ($options['json']['text']['preview_url'] ?? null) === true;
				}),
			)
			->willReturn($this->createJsonResponse(['messages' => [['id' => 'wamid.1']]]));

		$this->gateway->send('+55 (21) 99340-8474', 'POC message');
	}

	public function testSendThrowsWhenIdentifierIsInvalid(): void {
		$this->expectException(MessageTransmissionException::class);
		$this->expectExceptionMessage('Invalid phone number for WhatsApp Business.');

		$this->gateway->send('not-a-phone', 'POC message');
	}

	public function testSendThrowsProviderErrorMessageWhenGraphReturnsErrorPayload(): void {
		$this->gateway->setApiVersion('v25.0');
		$this->gateway->setPhoneNumberId('1068309859700859');
		$this->gateway->setAccessToken('token-123');

		$this->client->expects($this->once())
			->method('post')
			->willReturn($this->createJsonResponse([
				'error' => [
					'message' => 'Unsupported post request',
				],
			]));

		$this->expectException(MessageTransmissionException::class);
		$this->expectExceptionMessage('Unsupported post request');

		$this->gateway->send('+55 (21) 99340-8474', 'POC message');
	}

	public function testSendWrapsUnexpectedClientException(): void {
		$this->gateway->setApiVersion('v25.0');
		$this->gateway->setPhoneNumberId('1068309859700859');
		$this->gateway->setAccessToken('token-123');

		$this->client->expects($this->once())
			->method('post')
			->willThrowException(new \RuntimeException('Network down'));

		$this->expectException(MessageTransmissionException::class);
		$this->expectExceptionMessage('Failed to send message through WhatsApp Business.');

		$this->gateway->send('+55 (21) 99340-8474', 'POC message');
	}

	public function testSendUsesConfiguredTemplateWhenTemplateNameIsConfigured(): void {
		$this->gateway->setApiVersion('v25.0');
		$this->gateway->setPhoneNumberId('1068309859700859');
		$this->gateway->setAccessToken('token-123');
		$this->gateway->setTemplateName('libresign_document_invite');
		$this->gateway->setTemplateLanguage('pt_BR');

		$this->client->expects($this->once())
			->method('post')
			->with(
				'https://graph.facebook.com/v25.0/1068309859700859/messages',
				$this->callback(static function (array $options): bool {
					$payload = $options['json'] ?? [];
					$parameters = $payload['template']['components'][0]['parameters'][0] ?? [];

					return ($payload['type'] ?? null) === 'template'
						&& ($payload['template']['name'] ?? null) === 'libresign_document_invite'
						&& ($payload['template']['language']['code'] ?? null) === 'pt_BR'
						&& ($parameters['type'] ?? null) === 'text'
						&& ($parameters['text'] ?? null) === 'Assine seu documento: https://libresign.coop/s/abc';
				}),
			)
			->willReturn($this->createJsonResponse(['messages' => [['id' => 'wamid.2']]]));

		$this->gateway->send('+55 (21) 99340-8474', 'Assine seu documento: https://libresign.coop/s/abc');
	}

	public function testSendUsesRuntimeTemplateOverridesFromExtra(): void {
		$this->gateway->setApiVersion('v25.0');
		$this->gateway->setPhoneNumberId('1068309859700859');
		$this->gateway->setAccessToken('token-123');

		$this->client->expects($this->once())
			->method('post')
			->with(
				'https://graph.facebook.com/v25.0/1068309859700859/messages',
				$this->callback(static function (array $options): bool {
					$payload = $options['json'] ?? [];
					$parameters = $payload['template']['components'][0]['parameters'][0] ?? [];

					return ($payload['type'] ?? null) === 'template'
						&& ($payload['template']['name'] ?? null) === 'poc_link_libresign'
						&& ($payload['template']['language']['code'] ?? null) === 'pt_BR'
						&& ($parameters['text'] ?? null) === 'Mensagem customizada do solicitante: https://libresign.coop/s/xyz';
				}),
			)
			->willReturn($this->createJsonResponse(['messages' => [['id' => 'wamid.3']]]));

		$this->gateway->send(
			'+55 (21) 99340-8474',
			'Mensagem customizada do solicitante: https://libresign.coop/s/xyz',
			[
				'template_name' => 'poc_link_libresign',
				'template_language' => 'pt_BR',
			],
		);
	}

	private function createJsonResponse(array $payload): IResponse {
		$stream = $this->createStub(StreamInterface::class);
		$stream->method('__toString')->willReturn((string)json_encode($payload));

		$response = $this->createStub(IResponse::class);
		$response->method('getBody')->willReturn($stream);

		return $response;
	}
}
