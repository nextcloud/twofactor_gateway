<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\FieldType;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class FieldQuestionPrompter {
	public function askValue(
		FieldDefinition $field,
		InputInterface $input,
		OutputInterface $output,
		QuestionHelper $helper,
	): string {
		$this->renderHelper($field, $output);
		$question = $this->buildQuestion($field);
		$answer = $helper->ask($input, $output, $question);

		if (FieldType::fromNullable($field->getType()) === FieldType::BOOLEAN) {
			return $answer === true ? '1' : '0';
		}

		return (string)$answer;
	}

	public function buildQuestion(FieldDefinition $field): Question {
		$type = FieldType::fromNullable($field->getType()) ?? FieldType::TEXT;
		$prompt = rtrim($field->prompt) . ' ';

		if ($type === FieldType::BOOLEAN) {
			$default = $this->toBooleanDefault($field->default);
			$suffix = $default ? '[Y/n] ' : '[y/N] ';
			return new ConfirmationQuestion($prompt . $suffix, $default);
		}

		$question = new Question($prompt, $field->default === '' ? null : $field->default);
		$question->setNormalizer(static fn ($value): string => trim((string)($value ?? '')));

		if ($type === FieldType::SECRET) {
			$question->setHidden(true);
			$question->setHiddenFallback(false);
		}

		$question->setValidator(function (mixed $value) use ($field, $type): string {
			$normalized = trim((string)($value ?? ''));

			if ($normalized === '') {
				if ($field->optional) {
					return '';
				}

				throw new \InvalidArgumentException('This value cannot be empty.');
			}

			if ($type === FieldType::INTEGER) {
				if (!preg_match('/^-?\\d+$/', $normalized)) {
					throw new \InvalidArgumentException('Please provide a valid integer value.');
				}

				$numericValue = (int)$normalized;
				if ($field->min !== null && $numericValue < $field->min) {
					throw new \InvalidArgumentException(sprintf('Value must be greater than or equal to %d.', $field->min));
				}
				if ($field->max !== null && $numericValue > $field->max) {
					throw new \InvalidArgumentException(sprintf('Value must be less than or equal to %d.', $field->max));
				}

				return (string)$numericValue;
			}

			return $normalized;
		});

		return $question;
	}

	private function toBooleanDefault(string $default): bool {
		$normalized = strtolower(trim($default));
		return in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true);
	}

	private function renderHelper(FieldDefinition $field, OutputInterface $output): void {
		$helperText = trim($field->helper);
		if ($helperText === '') {
			return;
		}

		$output->writeln('<comment>' . OutputFormatter::escape($helperText) . '</comment>');
	}
}
