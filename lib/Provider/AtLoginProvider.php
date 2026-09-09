<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider;

use OCP\Authentication\TwoFactorAuth\ILoginSetupProvider;
use OCP\Server;
use OCP\Template\ITemplate;
use OCP\Template\ITemplateManager;

class AtLoginProvider implements ILoginSetupProvider {

	public function __construct(
		private string $gateway,
		private bool $isComplete,
	) {
	}

	#[\Override]
	public function getBody(): ITemplate {
		$template = Server::get(ITemplateManager::class)->getTemplate('twofactor_gateway', 'loginsetup');
		$template->assign('gateway', $this->gateway);
		$template->assign('isComplete', $this->isComplete);
		return $template;
	}
}
