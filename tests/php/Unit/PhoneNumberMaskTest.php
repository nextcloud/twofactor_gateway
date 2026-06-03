<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit;

use OCA\TwoFactorGateway\PhoneNumberMask;
use PHPUnit\Framework\TestCase;

class PhoneNumberMaskTest extends TestCase {
	public function testMaskIdentifierMasksPhoneNumbers(): void {
		$this->assertSame('**********999', PhoneNumberMask::maskIdentifier('5511999999999'));
		$this->assertSame('+**********999', PhoneNumberMask::maskIdentifier('+5511999999999'));
	}

	public function testMaskIdentifierMasksGenericIdentifiersByKeepingTail(): void {
		$this->assertSame('********tos', PhoneNumberMask::maskIdentifier('vitormattos'));
		$this->assertSame('***', PhoneNumberMask::maskIdentifier('abc'));
	}

	public function testRedactMessageReturnsPlaceholder(): void {
		$this->assertSame('[redacted]', PhoneNumberMask::redactMessage('Two Factor Gateway test message'));
	}
}