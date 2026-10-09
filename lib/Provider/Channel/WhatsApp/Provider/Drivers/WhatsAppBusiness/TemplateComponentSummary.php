<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness;

/**
 * A transport-independent description of the parameters a Meta template needs.
 * The gateway currently supplies one body parameter and, for AUTHENTICATION,
 * the Copy Code button at index zero.
 */
final readonly class TemplateComponentSummary {
	/** @param list<string> $bodyVariables */
	private function __construct(
		public string $body,
		public string $header,
		public string $footer,
		public array $bodyVariables,
		public array $copyCodeButtonIndexes,
		public bool $hasUnsupportedOtpButton,
		public bool $hasDynamicHeader,
		public bool $hasDynamicButton,
		public bool $hasUnsupportedComponent,
	) {
	}

	public static function fromMeta(mixed $rawComponents): self {
		if (!is_array($rawComponents)) {
			return new self('', '', '', [], [], false, false, false, true);
		}

		$body = '';
		$header = '';
		$footer = '';
		$bodyCount = 0;
		$buttonComponentCount = 0;
		$copyCodeIndexes = [];
		$hasUnsupportedOtpButton = false;
		$hasDynamicHeader = false;
		$hasDynamicButton = false;
		$hasUnsupportedComponent = false;

		foreach ($rawComponents as $component) {
			if (!is_array($component)) {
				$hasUnsupportedComponent = true;
				continue;
			}

			switch (self::upper($component['type'] ?? null)) {
				case 'BODY':
					$bodyCount++;
					$body = self::text($component['text'] ?? null);
					break;
				case 'HEADER':
					$header = self::text($component['text'] ?? null);
					$hasDynamicHeader = $hasDynamicHeader
						|| self::hasVariable($header)
						|| self::upper($component['format'] ?? 'TEXT') !== 'TEXT';
					break;
				case 'FOOTER':
					$footer = self::text($component['text'] ?? null);
					$hasUnsupportedComponent = $hasUnsupportedComponent || self::hasVariable($footer);
					break;
				case 'BUTTONS':
					$buttonComponentCount++;
					$buttons = $component['buttons'] ?? null;
					if (!is_array($buttons)) {
						$hasUnsupportedComponent = true;
						break;
					}
					foreach ($buttons as $index => $button) {
						if (!is_array($button)) {
							$hasUnsupportedComponent = true;
							continue;
						}
						if (self::upper($button['type'] ?? null) === 'OTP') {
							if (self::upper($button['otp_type'] ?? null) === 'COPY_CODE') {
								$copyCodeIndexes[] = (int)$index;
							} else {
								$hasUnsupportedOtpButton = true;
							}
						} else {
							$hasDynamicButton = $hasDynamicButton
								|| self::hasVariable(self::text($button['url'] ?? null));
						}
					}
					break;
				default:
					$hasUnsupportedComponent = true;
			}
		}

		preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $matches);
		return new self(
			$body,
			$header,
			$footer,
			$matches[1] ?? [],
			$copyCodeIndexes,
			$hasUnsupportedOtpButton,
			$hasDynamicHeader,
			$hasDynamicButton,
			$hasUnsupportedComponent || $bodyCount !== 1 || $buttonComponentCount > 1,
		);
	}

	public function hasSingleBodyVariable(): bool {
		return $this->bodyVariables === ['1'];
	}

	public function hasCopyCodeButtonAtIndexZero(): bool {
		return $this->copyCodeButtonIndexes === [0];
	}

	private static function text(mixed $value): string {
		return is_string($value) ? $value : '';
	}

	private static function upper(mixed $value): string {
		return strtoupper(trim(self::text($value)));
	}

	private static function hasVariable(string $value): bool {
		return preg_match('/\{\{\s*\d+\s*\}\}/', $value) === 1;
	}
}
