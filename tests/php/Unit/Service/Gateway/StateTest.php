<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service\Gateway;

use OCA\TwoFactorGateway\Service\Gateway\State;
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
