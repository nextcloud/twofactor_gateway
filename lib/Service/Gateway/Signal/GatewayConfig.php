<?php

declare(strict_types=1);

/**
 * @author Pascal Clémot <pascal.clemot@free.fr>
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

namespace OCA\TwoFactorGateway\Service\Gateway\Signal;

use OCA\TwoFactorGateway\Service\Gateway\IGatewayConfig;

class GatewayConfig implements IGatewayConfig {

	public function isComplete(): bool {
		// TODO: https://github.com/nextcloud/twofactor_gateway/issues/84
		return true;
	}

}