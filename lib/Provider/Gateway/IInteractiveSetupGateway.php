<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Gateway;

interface IInteractiveSetupGateway {
	/**
	 * @param array<string, string> $input
	 * @return array<string, mixed>
	 */
	public function interactiveSetupStart(array $input): array;

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function interactiveSetupStep(string $sessionId, string $action, array $input = []): array;

	/**
	 * @return array<string, mixed>
	 */
	public function interactiveSetupCancel(string $sessionId): array;
}
