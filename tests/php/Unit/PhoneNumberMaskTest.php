<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit;

use OCA\TwoFactorGateway\PhoneNumberMask;
use PHPUnit\Framework\TestCase;

class PhoneNumberMaskTest extends TestCase {
        public function testMasksAllButLastThreeDigits(): void {
                $masked = PhoneNumberMask::maskNumber('123456789');

                $this->assertSame('******789', $masked);
        }

        public function testDoesNotErrorOnShortNumbers(): void {
                $masked = PhoneNumberMask::maskNumber('12');

                $this->assertSame('12', $masked);
        }
}
