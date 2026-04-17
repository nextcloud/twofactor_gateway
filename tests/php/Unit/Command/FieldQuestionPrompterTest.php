<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Command;

use OCA\TwoFactorGateway\Command\FieldQuestionPrompter;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class FieldQuestionPrompterTest extends TestCase {
	public function testBuildQuestionMarksSecretAsHidden(): void {
		$prompter = new FieldQuestionPrompter();
		$field = new FieldDefinition(field: 'token', prompt: 'Token:', type: 'secret');

		$question = $prompter->buildQuestion($field);

		$this->assertInstanceOf(Question::class, $question);
		$this->assertTrue($question->isHidden());
	}

	public function testBuildQuestionUsesConfirmationForBoolean(): void {
		$prompter = new FieldQuestionPrompter();
		$field = new FieldDefinition(field: 'enabled', prompt: 'Enabled:', type: 'boolean', default: '1', optional: true);

		$question = $prompter->buildQuestion($field);

		$this->assertInstanceOf(ConfirmationQuestion::class, $question);
	}

	public function testAskValueNormalizesIntegerAndEnforcesBounds(): void {
		$prompter = new FieldQuestionPrompter();
		$field = new FieldDefinition(field: 'interval', prompt: 'Interval:', type: 'integer', min: 0, max: 60, optional: false);
		$helper = new QuestionHelper();
		$output = new BufferedOutput();

		$input = new StringInput('');
		$stream = fopen('php://memory', 'r+');
		fwrite($stream, "-1\n61\n42\n");
		rewind($stream);
		$input->setStream($stream);

		$value = $prompter->askValue($field, $input, $output, $helper);
		$this->assertSame('42', $value);
	}

	public function testAskValueReturnsBooleanAsOneOrZero(): void {
		$prompter = new FieldQuestionPrompter();
		$field = new FieldDefinition(field: 'enabled', prompt: 'Enabled:', type: 'boolean', default: '0', optional: true);
		$helper = new QuestionHelper();
		$output = new BufferedOutput();

		$input = new StringInput('');
		$stream = fopen('php://memory', 'r+');
		fwrite($stream, "y\n");
		rewind($stream);
		$input->setStream($stream);

		$value = $prompter->askValue($field, $input, $output, $helper);
		$this->assertSame('1', $value);
	}
}
