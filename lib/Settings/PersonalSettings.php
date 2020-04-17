<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * Nextcloud - Two-factor Gateway
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\TwoFactorGateway\Settings;

use OCP\Authentication\TwoFactorAuth\IPersonalProviderSettings;
use OCP\Template;

class PersonalSettings implements IPersonalProviderSettings {

	/** @var string */
	private $gateway;

	public function __construct(string $gateway) {
		$this->gateway = $gateway;
	}

	/**
	 * @return Template
	 *
	 * @since 15.0.0
	 */
	public function getBody(): Template {
		$tmpl = new Template('twofactor_gateway', 'personal_settings');
		$tmpl->assign('gateway', $this->gateway);
		return $tmpl;
	}
}
