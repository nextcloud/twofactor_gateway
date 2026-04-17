<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\XMPP;

use OCA\TwoFactorGateway\Provider\Channel\XMPP\Gateway;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GatewayTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private LoggerInterface&MockObject $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
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
}
