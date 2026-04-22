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
		/**
		 * Optional input type metadata for admin UIs and CLI prompts.
		 */
		public string|FieldType|null $type = null,
		/**
		 * Hidden fields are persisted but not rendered as editable inputs.
		 */
		public bool $hidden = false,
		/**
		 * Optional minimum numeric value constraint.
		 */
		public ?int $min = null,
		/**
		 * Optional maximum numeric value constraint.
		 */
		public ?int $max = null,
		/**
		 * Optional helper text displayed separately from the main prompt.
		 */
		public string $helper = '',
	) {
	}

	public function getType(): ?string {
		if ($this->type instanceof FieldType) {
			return $this->type->value;
		}

		return $this->type;
	}

	#[\Override]
	public function jsonSerialize(): mixed {
		return [
			'field' => $this->field,
			'prompt' => $this->prompt,
			'default' => $this->default,
			'optional' => $this->optional,
			'type' => $this->getType(),
			'hidden' => $this->hidden,
			'min' => $this->min,
			'max' => $this->max,
			'helper' => $this->helper,
		];
	}
}
