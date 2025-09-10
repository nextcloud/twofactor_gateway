<?php

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway;

class State {
	public const DISABLED = 0;
	public const VERIFYING = 1;
	public const ENABLED = 2;

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
