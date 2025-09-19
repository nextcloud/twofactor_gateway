<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider;

use OCA\TwoFactorGateway\Service\Gateway\SMS\Gateway as SMSGateway;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\ISession;
use OCP\Security\ISecureRandom;
use OCP\Template\ITemplateManager;

class SmsProvider extends AProvider {
	public function __construct(
		SMSGateway $smsGateway,
		StateStorage $stateStorage,
		ISession $session,
		ISecureRandom $secureRandom,
		IL10N $l10n,
		ITemplateManager $templateManager,
		IInitialState $initialState,
	) {
		parent::__construct(
			'sms',
			$smsGateway,
			$stateStorage,
			$session,
			$secureRandom,
			$l10n,
			$templateManager,
			$initialState,
		);
	}

	#[\Override]
	public function getDisplayName(): string {
		return $this->l10n->t('Message gateway verification');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l10n->t('Authenticate via SMS');
	}
}
