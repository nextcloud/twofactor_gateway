<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness\TemplateMessageBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TemplateMessageBuilderTest extends TestCase {
	#[DataProvider('validUtilityCases')]
	public function testPreservesOrderedValues(array $extra, array $expected): void {
		$result = (new TemplateMessageBuilder())->build('Default message', $extra, 'UTILITY');
		$this->assertSame([[
			'type' => 'body',
			'parameters' => array_map(static fn (string $text): array => ['type' => 'text', 'text' => $text], $expected),
		]], $result);
	}

	public static function validUtilityCases(): array {
		return [
			'default single message' => [[], ['Default message']],
			'one' => [['body_parameters' => ['Name']], ['Name']],
			'two' => [['body_parameters' => ['Name', 'https://example.invalid/sign']], ['Name', 'https://example.invalid/sign']],
			'three and order' => [['body_parameters' => ['First', 'Third', 'Second']], ['First', 'Third', 'Second']],
			'explicit count' => [['body_parameters' => ['First', 'Second'], 'expected_body_parameter_count' => 2], ['First', 'Second']],
			'code supplied as one message' => [['code' => '482931'], ['482931']],
		];
	}

	#[DataProvider('invalidUtilityCases')]
	public function testRejectsIncompleteOrInvalidValues(array $extra): void {
		$this->expectException(\InvalidArgumentException::class);
		(new TemplateMessageBuilder())->build('Default message', $extra, 'UTILITY');
	}

	public static function invalidUtilityCases(): array {
		return [
			'null' => [['body_parameters' => null]],
			'empty' => [['body_parameters' => []]],
			'nonsequential keys' => [['body_parameters' => [1 => 'Second', 0 => 'First']]],
			'associative' => [['body_parameters' => ['name' => 'First']]],
			'missing' => [['body_parameters' => ['First'], 'expected_body_parameter_count' => 2]],
			'extra' => [['body_parameters' => ['First', 'Second'], 'expected_body_parameter_count' => 1]],
			'wrong expected' => [['body_parameters' => ['First'], 'expected_body_parameter_count' => 2]],
			'string count' => [['body_parameters' => ['First'], 'expected_body_parameter_count' => '1']],
			'null entry' => [['body_parameters' => ['First', null]]],
			'number entry' => [['body_parameters' => ['First', 42]]],
			'empty entry' => [['body_parameters' => ['First', '']]],
			'blank entry' => [['body_parameters' => ['First', '  ']]],
			'line break' => [['body_parameters' => ["First\nSecond"]]],
			'tab' => [['body_parameters' => ["First\tSecond"]]],
			'carriage return' => [['body_parameters' => ["First\rSecond"]]],
			'excessive spaces' => [['body_parameters' => ['First     Second']]],
			'oversized text' => [['body_parameters' => [str_repeat('A', 1025)]]],
			'explicit null count' => [['body_parameters' => ['First'], 'expected_body_parameter_count' => null]],
			'zero count' => [['body_parameters' => ['First'], 'expected_body_parameter_count' => 0]],
			'negative count' => [['body_parameters' => ['First'], 'expected_body_parameter_count' => -1]],
			'unknown category token' => [['code' => null]],
			'embedded control character' => [['body_parameters' => ["First\0Second"]]],
		];
	}

	public function testRejectsUnknownTemplateCategoryInsteadOfDroppingOtpRules(): void {
		$this->expectException(\InvalidArgumentException::class);
		(new TemplateMessageBuilder())->build('Notification', ['code' => '482931'], 'AUTHENTICATION_CHANGED');
	}

	public function testAuthenticationCopiesOtpToBodyAndButton(): void {
		$actual = (new TemplateMessageBuilder())->build('Login', ['code' => '482931'], 'AUTHENTICATION');
		$this->assertSame([
			['type' => 'body', 'parameters' => [['type' => 'text', 'text' => '482931']]],
			['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => '482931']]],
		], $actual);
	}

	#[DataProvider('invalidOtpCases')]
	public function testInvalidOtpDoesNotProduceComponents(array $extra): void {
		$this->expectException(\InvalidArgumentException::class);
		(new TemplateMessageBuilder())->build('Login', $extra, 'AUTHENTICATION');
	}

	public static function invalidOtpCases(): array {
		return [
			'missing' => [[]],
			'nonnumeric' => [['code' => 'bad']],
			'wrong type' => [['code' => 482931]],
			'different body' => [['code' => '482931', 'body_parameters' => ['changed']]],
			'two values' => [['code' => '482931', 'body_parameters' => ['482931', 'More']]],
		];
	}
}
