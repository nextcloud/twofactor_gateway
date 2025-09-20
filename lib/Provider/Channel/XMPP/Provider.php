<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Rainer Dohmen <rdohmen@pensionmoselblick.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\XMPP;

use OCA\TwoFactorGateway\Provider\AProvider;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\ISession;
use OCP\Security\ISecureRandom;
use OCP\Template\ITemplateManager;

class Provider extends AProvider {
	public function __construct(
		Gateway $gateway,
		StateStorage $stateStorage,
		ISession $session,
		ISecureRandom $secureRandom,
		IL10N $l10n,
		ITemplateManager $templateManager,
		IInitialState $initialState,
	) {
		parent::__construct(
			'xmpp',
			$gateway,
			$stateStorage,
			$session,
			$secureRandom,
			$l10n,
			$templateManager,
			$initialState,
		);
	}

	#[\Override]
	public function getId(): string {
		return 'gateway_xmpp';
	}

	#[\Override]
	public function getDisplayName(): string {
		return $this->l10n->t('XMPP verification');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l10n->t('Authenticate via XMPP');
	}
}
