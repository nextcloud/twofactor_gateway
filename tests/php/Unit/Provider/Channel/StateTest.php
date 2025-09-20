<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service\Provider\Gateway;

use OCA\TwoFactorGateway\Provider\Gateway\State;
use PHPUnit\Framework\TestCase;

class StateTest extends TestCase {
	public function testDisabled() {
		$state = State::disabled();

		$this->assertTrue($state->isDisabled());
		$this->assertFalse($state->isVerifying());
		$this->assertFalse($state->isEnabled());
	}

	public function testVerifying() {
		$state = State::verifying();

		$this->assertFalse($state->isDisabled());
		$this->assertTrue($state->isVerifying());
		$this->assertFalse($state->isEnabled());
	}

	public function testEnabled() {
		$state = State::enabled();

		$this->assertFalse($state->isDisabled());
		$this->assertFalse($state->isVerifying());
		$this->assertTrue($state->isEnabled());
	}
}
