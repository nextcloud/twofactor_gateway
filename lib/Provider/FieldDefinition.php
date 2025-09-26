<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider;

use JsonSerializable;

class FieldDefinition implements JsonSerializable {
	public function __construct(
		/**
		 * The key that will store the value into database. Mandatory to have this item.
		 */
		public string $field,
		/**
		 * The label that will be displayed. Mandatory
		 */
		public string $prompt,
		/**
		 * The default value when the value isn't provided.
		 * Not mandatory to have this item
		 */
		public string $default = '',
		/**
		 * Default: false. Not mandatory to have this item
		 */
		public bool $optional = false,
	) {
	}

	#[\Override]
	public function jsonSerialize(): mixed {
		return [
			'field' => $this->field,
			'prompt' => $this->prompt,
			'default' => $this->default,
			'optional' => $this->optional,
		];
	}
}
