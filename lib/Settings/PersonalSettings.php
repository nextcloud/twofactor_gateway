<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Settings;

use OCP\Authentication\TwoFactorAuth\IPersonalProviderSettings;
use OCP\Server;
use OCP\Template\ITemplate;
use OCP\Template\ITemplateManager;

class PersonalSettings implements IPersonalProviderSettings {

	/** @var string */
	private $gateway;

	public function __construct(string $gateway) {
		$this->gateway = $gateway;
	}

	#[\Override]
	public function getBody(): ITemplate {
		$template = Server::get(ITemplateManager::class)->getTemplate('twofactor_gateway', 'personal_settings');
		$template->assign('gateway', $this->gateway);
		return $template;
	}
}
