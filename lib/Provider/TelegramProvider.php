<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider;

use OCA\TwoFactorGateway\Service\Gateway\Telegram\Gateway;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\IL10N;
use OCP\ISession;
use OCP\Security\ISecureRandom;
use OCP\Template\ITemplateManager;

class TelegramProvider extends AProvider {
	public function __construct(Gateway $smsGateway,
		StateStorage $stateStorage,
		ISession $session,
		ISecureRandom $secureRandom,
		IL10N $l10n,
		ITemplateManager $templateManager,
	) {
		parent::__construct(
			'telegram',
			$smsGateway,
			$stateStorage,
			$session,
			$secureRandom,
			$l10n,
			$templateManager,
		);
	}

	#[\Override]
	public function getId(): string {
		return 'gateway_telegram';
	}

	#[\Override]
	public function getDisplayName(): string {
		return $this->l10n->t('Telegram verification');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l10n->t('Authenticate via Telegram');
	}
}
