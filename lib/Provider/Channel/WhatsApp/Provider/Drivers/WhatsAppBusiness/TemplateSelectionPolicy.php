<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness;

/** Pure policy: an empty rejection reason means the current sender can deliver the template. */
final class TemplateSelectionPolicy {
	public static function rejectionReason(
		string $status,
		string $category,
		TemplateComponentSummary $components,
	): string {
		if ($status !== 'APPROVED') {
			return 'Template is not approved.';
		}
		if ($components->hasUnsupportedComponent) {
			return 'Template contains unsupported components.';
		}
		if (!$components->hasSingleBodyVariable()) {
			return 'Template must contain exactly one body variable {{1}}.';
		}
		if ($components->hasDynamicHeader || $components->hasDynamicButton) {
			return 'Template requires header or button parameters this gateway cannot send.';
		}

		if ($category === 'AUTHENTICATION') {
			if (!$components->hasCopyCodeButtonAtIndexZero() || $components->hasUnsupportedOtpButton) {
				return 'Only one Copy Code OTP button at index zero is supported.';
			}
			return '';
		}

		if ($components->copyCodeButtonIndexes !== [] || $components->hasUnsupportedOtpButton) {
			return 'OTP buttons require a supported authentication template.';
		}
		return '';
	}
}
