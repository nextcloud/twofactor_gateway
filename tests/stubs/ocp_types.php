<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP {
	interface IL10N {
	}

	interface IConfig {
		public function getSystemValueString(string $key, string $default = ''): string;

		/**
		 * @param mixed $default
		 * @return mixed
		 */
		public function getSystemValue(string $key, mixed $default = null): mixed;
	}
}

namespace OCP\Files {
	interface IAppData {
		public function newFolder(string $name): mixed;

		public function getFolder(string $name): mixed;
	}

	class NotFoundException extends \Exception {
	}
}

namespace {
	class OC {
		public static string $SERVERROOT = '';
	}
}
