<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider;

use OCA\TwoFactorGateway\Service\Gateway\Signal\Gateway as SignalGateway;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\ISession;
use OCP\Security\ISecureRandom;
use OCP\Template\ITemplateManager;

class SignalProvider extends AProvider {
	public function __construct(
		SignalGateway $smsGateway,
		StateStorage $stateStorage,
		ISession $session,
		ISecureRandom $secureRandom,
		IL10N $l10n,
		ITemplateManager $templateManager,
		IInitialState $initialState,
	) {
		parent::__construct(
			'signal',
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
		return $this->l10n->t('Signal verification');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l10n->t('Authenticate via Signal');
	}
}
