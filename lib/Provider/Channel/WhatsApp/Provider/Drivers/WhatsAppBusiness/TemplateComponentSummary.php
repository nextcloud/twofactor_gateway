<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness;

/**
 * A transport-independent description of the parameters a Meta template needs.
 * This gateway supplies one body parameter and, for AUTHENTICATION, a Copy Code
 * button at index zero.
 */
final readonly class TemplateComponentSummary {
	/**
	 * @param list<string> $bodyVariables
	 * @param list<int> $copyCodeButtonIndexes
	 */
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

		[$components, $invalidComponents] = self::indexComponents($rawComponents);
		$body = self::text($components['BODY']['text'] ?? null);
		$header = self::text($components['HEADER']['text'] ?? null);
		$footer = self::text($components['FOOTER']['text'] ?? null);

		$hasDynamicHeader = isset($components['HEADER'])
			&& (self::hasVariable($header)
				|| self::upper($components['HEADER']['format'] ?? 'TEXT') !== 'TEXT');
		$rawButtons = isset($components['BUTTONS']) ? ($components['BUTTONS']['buttons'] ?? null) : [];
		[$copyCodeIndexes, $unsupportedOtp, $dynamicButton, $invalidButtons] = self::inspectButtons($rawButtons);

		preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $matches);
		return new self(
			$body,
			$header,
			$footer,
			$matches[1] ?? [],
			$copyCodeIndexes,
			$unsupportedOtp,
			$hasDynamicHeader,
			$dynamicButton,
			$invalidComponents || !isset($components['BODY']) || self::hasVariable($footer) || $invalidButtons,
		);
	}

	/** @return array{array<string, array<string, mixed>>, bool} */
	private static function indexComponents(array $rawComponents): array {
		$indexed = [];
		$invalid = false;
		foreach ($rawComponents as $component) {
			if (!is_array($component)) {
				$invalid = true;
				continue;
			}

			$type = self::upper($component['type'] ?? null);
			if (!in_array($type, ['BODY', 'HEADER', 'FOOTER', 'BUTTONS'], true) || isset($indexed[$type])) {
				$invalid = true;
				continue;
			}
			$indexed[$type] = $component;
		}
		return [$indexed, $invalid];
	}

	/**
	 * @return array{list<int>, bool, bool, bool}
	 */
	private static function inspectButtons(mixed $rawButtons): array {
		if (!is_array($rawButtons)) {
			return [[], false, false, true];
		}

		$copyCodeIndexes = [];
		$unsupportedOtp = false;
		$dynamicButton = false;
		$invalid = false;
		foreach ($rawButtons as $index => $button) {
			if (!is_array($button)) {
				$invalid = true;
				continue;
			}
			if (self::upper($button['type'] ?? null) !== 'OTP') {
				$dynamicButton = $dynamicButton
					|| self::hasVariable(self::text($button['url'] ?? null));
				continue;
			}
			if (self::upper($button['otp_type'] ?? null) !== 'COPY_CODE') {
				$unsupportedOtp = true;
				continue;
			}
			$copyCodeIndexes[] = (int)$index;
		}
		return [$copyCodeIndexes, $unsupportedOtp, $dynamicButton, $invalid];
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
