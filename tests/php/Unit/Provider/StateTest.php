<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider;

use OCA\TwoFactorGateway\Provider\State;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

class StateTest extends TestCase {
	public function testVerify() {
		$user = $this->createMock(IUser::class);
		$original = State::verifying(
			$user,
			'signal',
			'0123456789',
			'123456'
		);
		$expected = new State(
			$user,
			StateStorage::STATE_ENABLED,
			'signal',
			'0123456789',
			'123456'
		);

		$actual = $original->verify();

		$this->assertEquals($expected, $actual);
	}
}
