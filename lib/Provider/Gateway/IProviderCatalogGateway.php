<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Gateway;

use OCA\TwoFactorGateway\Provider\FieldDefinition;

interface IProviderCatalogGateway {
	public function getProviderSelectorField(): FieldDefinition;

	/**
	 * @return list<array{id: string, name: string, fields: list<FieldDefinition>}>
	 */
	public function getProviderCatalog(): array;
}
