<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Settings;

use OCP\Authentication\TwoFactorAuth\IPersonalProviderSettings;
use OCP\Template;

class PersonalSettings implements IPersonalProviderSettings {

	public function __construct(
		private string $gateway,
		private bool $isComplete,
	) {
	}

	#[\Override]
	public function getBody(): Template {
		$template = new Template('twofactor_gateway', 'personal_settings');
		$template->assign('gateway', $this->gateway);
		$template->assign('isComplete', $this->isComplete);
		return $template;
	}
}
