<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness;

/** Maps Meta's untrusted catalog response into the stable wizard view model. */
final class TemplateCatalogNormalizer {
	/**
	 * @param array<mixed> $rawTemplates
	 * @return list<array<string, string|bool>>
	 */
	public function normalize(array $rawTemplates): array {
		$templates = [];
		foreach ($rawTemplates as $rawTemplate) {
			if (!is_array($rawTemplate)) {
				continue;
			}

			$name = self::text($rawTemplate['name'] ?? null);
			$language = self::text($rawTemplate['language'] ?? null);
			if ($name === '' || $language === '') {
				continue;
			}

			$status = strtoupper(self::text($rawTemplate['status'] ?? null));
			$category = strtoupper(self::text($rawTemplate['category'] ?? null));
			$components = TemplateComponentSummary::fromMeta($rawTemplate['components'] ?? null);
			$reason = TemplateSelectionPolicy::rejectionReason($status, $category, $components);

			$templates[] = [
				'name' => $name,
				'language' => $language,
				'status' => $status,
				'category' => $category,
				'body' => $components->body,
				'header' => $components->header,
				'footer' => $components->footer,
				'is_selectable' => $reason === '',
				'unselectable_reason' => $reason,
			];
		}
		return $templates;
	}

	private static function text(mixed $value): string {
		return is_string($value) ? trim($value) : '';
	}
}
