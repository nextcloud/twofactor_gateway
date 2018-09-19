<?php

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * Nextcloud - Two-factor Gateway
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\TwoFactorGateway\Service\Gateway;


class State {

	const DISABLED = 0;
	const VERIFYING = 1;
	const ENABLED = 2;

	/** @var int */
	private $state;

	private function __construct(int $state) {
		$this->state = $state;
	}

	public static function disabled(): State {
		return new self(self::DISABLED);
	}

	public static function verifying(): State {
		return new self(self::VERIFYING);
	}

	public static function enabled(): State {
		return new self(self::ENABLED);
	}

	public function isDisabled(): bool {
		return $this->state === self::DISABLED;
	}

	public function isVerifying(): bool {
		return $this->state === self::VERIFYING;
	}

	public function isEnabled(): bool {
		return $this->state === self::ENABLED;
	}

}
