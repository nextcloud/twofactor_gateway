<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Rainer Dohmen <rdohmen@pensionmoselblick.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\XMPP;

use OCA\TwoFactorGateway\Provider\AProvider;

class Provider extends AProvider {

	#[\Override]
	public function getDisplayName(): string {
		return $this->l10n->t('XMPP verification');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l10n->t('Authenticate via XMPP');
	}
}
