<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness\TemplateCatalogNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TemplateCatalogNormalizerTest extends TestCase {
	#[DataProvider('eligibilityCases')]
	public function testCompatibilityDecision(
		string $status,
		string $category,
		mixed $components,
		bool $expectedSelectable,
		string $expectedReason,
	): void {
		$catalog = (new TemplateCatalogNormalizer())->normalize([[
			'name' => 'verification_code',
			'language' => 'pt_BR',
			'status' => $status,
			'category' => $category,
			'components' => $components,
		]]);
		$this->assertCount(1, $catalog);
		$this->assertSame($expectedSelectable, $catalog[0]['is_selectable']);
		$this->assertSame($expectedReason, $catalog[0]['unselectable_reason']);
	}

	public static function eligibilityCases(): array {
		$body = ['type' => 'BODY', 'text' => 'Your code is {{1}}'];
		$bodyWithoutVariable = ['type' => 'BODY', 'text' => 'Your code'];
		$copy = ['type' => 'OTP', 'otp_type' => 'COPY_CODE'];
		$copyButtons = ['type' => 'BUTTONS', 'buttons' => [$copy]];
		$oneTapButtons = ['type' => 'BUTTONS', 'buttons' => [
			['type' => 'OTP', 'otp_type' => 'ONE_TAP'],
		]];
		$staticUrl = ['type' => 'BUTTONS', 'buttons' => [
			['type' => 'URL', 'url' => 'https://example.com'],
		]];
		$dynamicUrl = ['type' => 'BUTTONS', 'buttons' => [
			['type' => 'URL', 'url' => 'https://example.com/{{1}}'],
		]];
		$notApproved = 'Template is not approved.';
		$badComponent = 'Template contains unsupported components.';
		$badBody = 'Template must contain exactly one body variable {{1}}.';
		$dynamic = 'Template requires header or button parameters this gateway cannot send.';
		$badCopy = 'Only one Copy Code OTP button at index zero is supported.';
		$badNonAuth = 'OTP buttons require a supported authentication template.';

		return [
			'approved copy code auth' => ['APPROVED', 'AUTHENTICATION', [$body, $copyButtons], true, ''],
			'case-insensitive status and category' => [' approved ', ' authentication ', [$body, $copyButtons], true, ''],
			'utility with one body variable' => ['APPROVED', 'UTILITY', [$body], true, ''],
			'marketing with fixed URL' => ['APPROVED', 'MARKETING', [$body, $staticUrl], true, ''],
			'unclassified legacy one variable' => ['APPROVED', '', [$body], true, ''],
			'not approved' => ['PENDING', 'AUTHENTICATION', [$body, $copyButtons], false, $notApproved],
			'authentication missing copy button' => ['APPROVED', 'AUTHENTICATION', [$body], false, $badCopy],
			'authentication one tap OTP' => ['APPROVED', 'AUTHENTICATION', [$body, $oneTapButtons], false, $badCopy],
			'authentication multiple copy buttons' => ['APPROVED', 'AUTHENTICATION', [
				$body, ['type' => 'BUTTONS', 'buttons' => [$copy, $copy]],
			], false, $badCopy],
			'copy code at nonzero index' => ['APPROVED', 'AUTHENTICATION', [
				$body, ['type' => 'BUTTONS', 'buttons' => [
					['type' => 'URL', 'url' => 'https://example.com'], $copy,
				]],
			], false, $badCopy],
			'authentication missing body parameter' => ['APPROVED', 'AUTHENTICATION', [$bodyWithoutVariable, $copyButtons], false, $badBody],
			'utility without body parameter' => ['APPROVED', 'UTILITY', [$bodyWithoutVariable], false, $badBody],
			'utility two body variables' => ['APPROVED', 'UTILITY', [
				['type' => 'BODY', 'text' => '{{1}} and {{2}}'],
			], false, $badBody],
			'duplicate first body variable' => ['APPROVED', 'UTILITY', [
				['type' => 'BODY', 'text' => '{{1}} and again {{1}}'],
			], false, $badBody],
			'header variable' => ['APPROVED', 'UTILITY', [
				$body, ['type' => 'HEADER', 'text' => 'Hello {{1}}'],
			], false, $dynamic],
			'media header' => ['APPROVED', 'UTILITY', [
				$body, ['type' => 'HEADER', 'format' => 'IMAGE'],
			], false, $dynamic],
			'dynamic URL button' => ['APPROVED', 'UTILITY', [$body, $dynamicUrl], false, $dynamic],
			'unsupported footer variable' => ['APPROVED', 'UTILITY', [
				$body, ['type' => 'FOOTER', 'text' => 'Footer {{1}}'],
			], false, $badComponent],
			'OTP on nonauthentication template' => ['APPROVED', 'UTILITY', [$body, $copyButtons], false, $badNonAuth],
			'invalid component structure' => ['APPROVED', 'UTILITY', [$body, null], false, $badComponent],
			'unknown component type' => ['APPROVED', 'UTILITY', [$body, ['type' => 'CAROUSEL']], false, $badComponent],
			'duplicate body definitions' => ['APPROVED', 'UTILITY', [$body, $body], false, $badComponent],
			'invalid buttons structure' => ['APPROVED', 'UTILITY', [$body, ['type' => 'BUTTONS', 'buttons' => 'invalid']], false, $badComponent],
			'missing components' => ['APPROVED', 'UTILITY', null, false, $badComponent],
		];
	}

	public function testNormalizerPreservesPreviewAndRejectsMalformedCatalogEntries(): void {
		$input = [
			null,
			['name' => '', 'language' => 'pt_BR'],
			['name' => ['malformed'], 'language' => 'pt_BR'],
			['name' => 'first', 'language' => 'pt_BR', 'status' => 'approved',
				'category' => 'utility', 'components' => [
					['type' => 'HEADER', 'text' => 'Static header'],
					['type' => 'BODY', 'text' => 'Use {{ 1 }} to login'],
					['type' => 'FOOTER', 'text' => 'Static footer'],
				]],
			['name' => 'second', 'language' => 'en_US', 'status' => 'REJECTED',
				'category' => 'UTILITY', 'components' => [
					['type' => 'BODY', 'text' => 'Use {{1}}'],
				]],
		];

		$actual = (new TemplateCatalogNormalizer())->normalize($input);

		$this->assertCount(2, $actual);
		$this->assertSame([
			'name' => 'first',
			'language' => 'pt_BR',
			'status' => 'APPROVED',
			'category' => 'UTILITY',
			'body' => 'Use {{ 1 }} to login',
			'header' => 'Static header',
			'footer' => 'Static footer',
			'is_selectable' => true,
			'unselectable_reason' => '',
		], $actual[0]);
		$this->assertSame('Template is not approved.', $actual[1]['unselectable_reason']);
	}
}
