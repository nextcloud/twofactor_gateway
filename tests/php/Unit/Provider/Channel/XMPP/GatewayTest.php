<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\XMPP;

use OCA\TwoFactorGateway\PhoneNumberMask;
use OCA\TwoFactorGateway\Provider\Channel\XMPP\CurlFunctionStubs;
use OCA\TwoFactorGateway\Provider\Channel\XMPP\Gateway;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\FieldExposure;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/CurlFunctionStubs.php';

class GatewayTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private LoggerInterface&MockObject $logger;
	/** @var array<string, string> */
	private array $appConfigValues = [];

	protected function setUp(): void {
		parent::setUp();
		$this->appConfigValues = [];
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig->method('setValueString')
			->willReturnCallback(function (string $app, string $key, string $value): bool {
				$this->appConfigValues[$app . '::' . $key] = $value;
				return true;
			});
		$this->appConfig->method('getValueString')
			->willReturnCallback(function (string $app, string $key, string $default = ''): string {
				return $this->appConfigValues[$app . '::' . $key] ?? $default;
			});
		$this->appConfig->method('deleteKey')
			->willReturnCallback(function (string $app, string $key): void {
				unset($this->appConfigValues[$app . '::' . $key]);
			});
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	public function testCreateSettingsHasUsernamePromptConfigured(): void {
		$gateway = new Gateway($this->appConfig, $this->logger);
		$settings = $gateway->getSettings();

		$fieldByName = [];
		foreach ($settings->fields as $field) {
			$fieldByName[$field->field] = $field;
		}

		$this->assertArrayHasKey('username', $fieldByName);
		$this->assertNotSame('', trim($fieldByName['username']->prompt));
	}

	public function testCreateSettingsDoesNotExposeFieldsWithEmptyPrompt(): void {
		$gateway = new Gateway($this->appConfig, $this->logger);
		$settings = $gateway->getSettings();

		$emptyPromptFields = array_filter(
			$settings->fields,
			static fn (FieldDefinition $field): bool => trim($field->prompt) === '',
		);

		$this->assertCount(0, $emptyPromptFields);
	}

	public function testCreateSettingsMarksFieldsAsAdminOnly(): void {
		$gateway = new Gateway($this->appConfig, $this->logger);
		$settings = $gateway->getSettings();

		foreach ($settings->fields as $field) {
			$this->assertSame(FieldExposure::ADMIN->value, $field->getExposure());
		}
	}

	public function testSendMasksIdentifierAndDoesNotLogMessageContent(): void {
		CurlFunctionStubs::reset();

		$gateway = new Gateway($this->appConfig, $this->logger);
		$gateway->setSender('sender@example.com');
		$gateway->setPassword('secret-password');
		$gateway->setServer('https://xmpp.local/messages/');
		$gateway->setUsername('sender');
		$gateway->setMethod('2');

		$maskedIdentifier = PhoneNumberMask::maskIdentifier('user@example.com');
		$debugEntries = [];
		$this->logger->expects($this->exactly(3))
			->method('debug')
			->willReturnCallback(static function (string $message, array $context = []) use (&$debugEntries): void {
				$debugEntries[] = [
					'message' => $message,
					'context' => $context,
				];
			});

		$gateway->send('user@example.com', '123456 is your verification code.');

		$this->assertCount(3, $debugEntries);
		$this->assertSame('sending xmpp message to ' . $maskedIdentifier, $debugEntries[0]['message']);
		$this->assertSame('Preparing XMPP request', $debugEntries[1]['message']);
		$this->assertSame($maskedIdentifier, $debugEntries[1]['context']['recipient'] ?? null);
		$this->assertSame('2', $debugEntries[1]['context']['method'] ?? null);
		$this->assertSame('XMPP message to ' . $maskedIdentifier . ' sent', $debugEntries[2]['message']);

		foreach ($debugEntries as $entry) {
			$this->assertStringNotContainsString('user@example.com', $entry['message']);
			$this->assertStringNotContainsString('123456 is your verification code.', $entry['message']);
		}

		$this->assertContains(
			['option' => CURLOPT_POSTFIELDS, 'value' => '123456 is your verification code.'],
			CurlFunctionStubs::$setoptCalls,
		);
	}
}
