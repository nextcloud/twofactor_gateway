<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\TwoFactorGateway\Tests\Unit\Command;

use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Coopied from BufferedOutput because Nextcloud expects ConsoleOutputInterface
 * but we need to be able to fetch the output.
 */
class ConsoleOutputSpy extends ConsoleOutput {
	private $buffer = '';

	/**
	 * Empties buffer and returns its content.
	 *
	 * @return string
	 */
	public function fetch() {
		$content = $this->buffer;
		$this->buffer = '';

		return $content;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doWrite(string $message, bool $newline) {
		$this->buffer .= $message;

		if ($newline) {
			$this->buffer .= \PHP_EOL;
		}
	}
}
