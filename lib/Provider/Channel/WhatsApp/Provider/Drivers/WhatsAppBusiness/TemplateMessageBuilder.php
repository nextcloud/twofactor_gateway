<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness;

/**
 * Converts positional values into Meta body and OTP button components.
 * The caller supplies meaning and order; this class validates only transport rules.
 */
final class TemplateMessageBuilder {
	/**
	 * @param array<string, mixed> $extra
	 * @return list<array<string, mixed>>
	 */
	public function build(string $message, array $extra, string $category): array {
		if (!in_array($category, ['', 'UTILITY', 'MARKETING', 'AUTHENTICATION'], true)) {
			throw new \InvalidArgumentException('Unsupported WhatsApp Business template category.');
		}
		$code = $this->code($extra, $category);
		$values = $this->values($message, $extra, $code);
		$this->validateCount($extra, $values);
		$this->validateAuthenticationBody($category, $values, $code);

		$components = [['type' => 'body', 'parameters' => array_map($this->textParameter(...), $values)]];
		if ($category === 'AUTHENTICATION') {
			$components[] = $this->copyCodeButton($code);
		}
		return $components;
	}

	/** @param array<string, mixed> $extra */
	private function code(array $extra, string $category): string {
		$candidate = array_key_exists('code', $extra) ? $extra['code'] : '';
		if (!is_string($candidate)) {
			throw new \InvalidArgumentException('Invalid authentication code type.');
		}
		if ($category === 'AUTHENTICATION' && preg_match('/^[0-9]{4,15}$/D', $candidate) !== 1) {
			throw new \InvalidArgumentException('Authentication template requires a numeric OTP code.');
		}
		return $category === 'AUTHENTICATION' ? $candidate : trim($candidate);
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return list<mixed>
	 */
	private function values(string $message, array $extra, string $code): array {
		// An absent option uses the current single-parameter message contract.
		// Explicitly invalid values must never fall back to $message.
		$values = array_key_exists('body_parameters', $extra)
			? $extra['body_parameters']
			: [$code !== '' ? $code : $message];
		if (!is_array($values) || !array_is_list($values) || $values === []) {
			throw new \InvalidArgumentException('Template body parameters must be a non-empty ordered list.');
		}
		return $values;
	}

	/**
	 * @param array<string, mixed> $extra
	 * @param list<mixed> $values
	 */
	private function validateCount(array $extra, array $values): void {
		if (!array_key_exists('expected_body_parameter_count', $extra)) {
			return;
		}
		$expected = $extra['expected_body_parameter_count'];
		if (!is_int($expected) || $expected < 1 || $expected !== count($values)) {
			throw new \InvalidArgumentException('Invalid expected template body parameter count.');
		}
	}

	/** @param list<mixed> $values */
	private function validateAuthenticationBody(string $category, array $values, string $code): void {
		if ($category === 'AUTHENTICATION' && (count($values) !== 1 || $values[0] !== $code)) {
			throw new \InvalidArgumentException('Authentication template requires its OTP as the only body parameter.');
		}
	}

	/** @return array{type: string, text: string} */
	private function textParameter(mixed $value): array {
		if (!is_string($value) || trim($value) === '' || !mb_check_encoding($value, 'UTF-8')) {
			throw new \InvalidArgumentException('Template body parameters must be non-empty strings.');
		}
		if (preg_match('/[\x00-\x1F\x7F]| {5}/', $value) === 1) {
			throw new \InvalidArgumentException('Template body parameters cannot contain line breaks, tabs or excessive spaces.');
		}
		if (mb_strlen($value, 'UTF-8') > 1024) {
			throw new \InvalidArgumentException('Template body parameter exceeds the supported text length.');
		}
		return ['type' => 'text', 'text' => $value];
	}

	/** @return array<string, mixed> */
	private function copyCodeButton(string $code): array {
		return [
			'type' => 'button',
			'sub_type' => 'url',
			'index' => '0',
			'parameters' => [['type' => 'text', 'text' => $code]],
		];
	}
}
