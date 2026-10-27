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
	public function testPreservesOrderedValues(array $extra, ?int $count, array $expected): void {
		$result = (new TemplateMessageBuilder())->build('Legacy message', $extra, 'UTILITY', $count);
		$this->assertSame([[
			'type' => 'body',
			'parameters' => array_map(static fn (string $text): array => ['type' => 'text', 'text' => $text], $expected),
		]], $result);
	}

	public static function validUtilityCases(): array {
		return [
			'legacy' => [[], null, ['Legacy message']],
			'one' => [['body_parameters' => ['Name']], 1, ['Name']],
			'two' => [['body_parameters' => ['Name', 'https://example.invalid/sign']], 2, ['Name', 'https://example.invalid/sign']],
			'three and order' => [['body_parameters' => ['First', 'Third', 'Second']], 3, ['First', 'Third', 'Second']],
			'explicit count' => [['body_parameters' => ['First', 'Second'], 'expected_body_parameter_count' => 2], null, ['First', 'Second']],
			'legacy code' => [['code' => '482931'], null, ['482931']],
		];
	}

	#[DataProvider('invalidUtilityCases')]
	public function testRejectsIncompleteOrInvalidValues(array $extra, ?int $count): void {
		$this->expectException(\InvalidArgumentException::class);
		(new TemplateMessageBuilder())->build('Legacy message', $extra, 'UTILITY', $count);
	}

	public static function invalidUtilityCases(): array {
		return [
			'null' => [['body_parameters' => null], null],
			'empty' => [['body_parameters' => []], null],
			'nonsequential keys' => [['body_parameters' => [1 => 'Second', 0 => 'First']], null],
			'associative' => [['body_parameters' => ['name' => 'First']], null],
			'missing' => [['body_parameters' => ['First']], 2],
			'extra' => [['body_parameters' => ['First', 'Second']], 1],
			'wrong expected' => [['body_parameters' => ['First'], 'expected_body_parameter_count' => 2], null],
			'string count' => [['body_parameters' => ['First'], 'expected_body_parameter_count' => '1'], null],
			'null entry' => [['body_parameters' => ['First', null]], null],
			'number entry' => [['body_parameters' => ['First', 42]], null],
			'empty entry' => [['body_parameters' => ['First', '']], null],
			'blank entry' => [['body_parameters' => ['First', '  ']], null],
			'line break' => [['body_parameters' => ["First\nSecond"]], null],
			'tab' => [['body_parameters' => ["First\tSecond"]], null],
			'carriage return' => [['body_parameters' => ["First\rSecond"]], null],
			'excessive spaces' => [['body_parameters' => ['First     Second']], null],
			'oversized text' => [['body_parameters' => [str_repeat('A', 1025)]], null],
		];
	}

	public function testAuthenticationCopiesOtpToBodyAndButton(): void {
		$actual = (new TemplateMessageBuilder())->build('Login', ['code' => '482931'], 'AUTHENTICATION', 1);
		$this->assertSame([
			['type' => 'body', 'parameters' => [['type' => 'text', 'text' => '482931']]],
			['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => '482931']]],
		], $actual);
	}

	#[DataProvider('invalidOtpCases')]
	public function testInvalidOtpDoesNotProduceComponents(array $extra): void {
		$this->expectException(\InvalidArgumentException::class);
		(new TemplateMessageBuilder())->build('Login', $extra, 'AUTHENTICATION', 1);
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
