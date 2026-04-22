<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\GoWhatsApp;

use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\GoWhatsApp\HealthRiskScorer;
use PHPUnit\Framework\TestCase;

class HealthRiskScorerTest extends TestCase {
	private HealthRiskScorer $scorer;

	protected function setUp(): void {
		$this->scorer = new HealthRiskScorer();
	}

	// -------------------------------------------------------------------------
	// computeScore – baselines
	// -------------------------------------------------------------------------

	public function testEmptyHistoryScoresZero(): void {
		$this->assertSame(0, $this->scorer->computeScore([]));
	}

	public function testHealthyEntriesContributeZeroPoints(): void {
		$this->assertSame(0, $this->scorer->computeScore([
			['ts' => 1000, 'state' => 'connected'],
			['ts' => 1060, 'state' => 'logged_in'],
		]));
	}

	// -------------------------------------------------------------------------
	// computeScore – individual state weights + oscillation from healthy base
	// -------------------------------------------------------------------------

	public function testDisconnectedFromHealthyBaselineScoresThirty(): void {
		// weight(disconnected)=20  + oscillation_penalty=10 (prev was healthy baseline)
		$this->assertSame(30, $this->scorer->computeScore([
			['ts' => 1000, 'state' => 'disconnected'],
		]));
	}

	public function testConnectingFromHealthyBaselineScoresTwenty(): void {
		// weight(connecting)=10 + oscillation_penalty=10
		$this->assertSame(20, $this->scorer->computeScore([
			['ts' => 1000, 'state' => 'connecting'],
		]));
	}

	public function testUnreachableFromHealthyBaselineScoresSixty(): void {
		// weight(unreachable)=50 + oscillation_penalty=10
		$this->assertSame(60, $this->scorer->computeScore([
			['ts' => 1000, 'state' => 'unreachable'],
		]));
	}

	public function testUnknownStateFallsBackToDefaultWeightTwentyFive(): void {
		// DEFAULT_STATE_WEIGHT=15 + oscillation_penalty=10
		$this->assertSame(25, $this->scorer->computeScore([
			['ts' => 1000, 'state' => 'some_new_state'],
		]));
	}

	// -------------------------------------------------------------------------
	// computeScore – oscillation penalty logic
	// -------------------------------------------------------------------------

	public function testConsecutiveUnhealthyDoesNotRepeatOscillationPenalty(): void {
		// entry 1: 20+10=30  (oscillation, prev was healthy baseline)
		// entry 2: 20+0 =20  (no oscillation, prev was unhealthy)
		$this->assertSame(50, $this->scorer->computeScore([
			['ts' => 1000, 'state' => 'disconnected'],
			['ts' => 1060, 'state' => 'disconnected'],
		]));
	}

	public function testOscillationAddsExtraPenaltyOnEachHealthyToUnhealthyTransition(): void {
		// entry 1 disconnected: 20+10=30 (from healthy baseline)
		// entry 2 connected:     0
		// entry 3 disconnected: 20+10=30 (transition healthy→unhealthy again)
		$this->assertSame(60, $this->scorer->computeScore([
			['ts' => 1000, 'state' => 'disconnected'],
			['ts' => 1060, 'state' => 'connected'],
			['ts' => 1120, 'state' => 'disconnected'],
		]));
	}

	public function testHealthyEntryBetweenUnhealthyResetsOscillationTracking(): void {
		// Make sure a healthy entry really resets "prevHealthy" so the next
		// unhealthy one gets the oscillation bonus again.
		$score = $this->scorer->computeScore([
			['ts' => 1000, 'state' => 'disconnected'], // 30
			['ts' => 1060, 'state' => 'logged_in'],    // 0  – resets tracking
			['ts' => 1120, 'state' => 'connecting'],   // 10+10=20 (oscillation applies)
		]);
		$this->assertSame(50, $score);
	}

	// -------------------------------------------------------------------------
	// buildReason – content validation
	// -------------------------------------------------------------------------

	public function testBuildReasonContainsRiskScoreAndStateCounts(): void {
		$history = [
			['ts' => 1000, 'state' => 'disconnected'],
			['ts' => 1060, 'state' => 'disconnected'],
			['ts' => 1120, 'state' => 'connecting'],
		];
		$reason = $this->scorer->buildReason($history, 99);

		$this->assertStringContainsString('risk score: 99', $reason);
		$this->assertStringContainsString('2 × disconnected', $reason);
		$this->assertStringContainsString('1 × connecting', $reason);
	}

	public function testBuildReasonExcludesHealthyStates(): void {
		$history = [
			['ts' => 1000, 'state' => 'connected'],
			['ts' => 1060, 'state' => 'disconnected'],
		];
		$reason = $this->scorer->buildReason($history, 30);

		$this->assertStringNotContainsString('× connected', $reason);
		$this->assertStringContainsString('1 × disconnected', $reason);
	}

	public function testBuildReasonIncludesStandardClosingMessage(): void {
		$reason = $this->scorer->buildReason([], 0);
		$this->assertStringContainsString('re-authentication', $reason);
	}
}
