<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway;

interface IGatewayConfig {
	public function getOrFail(string $key): string;
	public function isComplete(): bool;
	public function remove(): void;
	public static function providerId(): string;
}
