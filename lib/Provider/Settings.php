<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider;

use JsonSerializable;

class Settings implements JsonSerializable {
	public function __construct(
		public string $name,
		public string $id = '',
		public bool $allowMarkdown = false,
		public string $instructions = '',
		/**
		 * List of fields to be filled by system administrators when configuring the gateway.
		 *
		 * @var FieldDefinition[]
		 */
		public array $fields = [],
	) {
	}

	#[\Override]
	public function jsonSerialize(): mixed {
		return [
			'name' => $this->name,
			'id' => $this->id,
			'allow_markdown' => $this->allowMarkdown,
			'instructions' => $this->instructions,
			'fields' => $this->fields,
		];
	}

	protected function resolveId(): string {
		if (!empty($this->id)) {
			return $this->id;
		}
		return strtolower((new \ReflectionClass($this))->getShortName());
	}
}
