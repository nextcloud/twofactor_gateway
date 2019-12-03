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

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Exception\InvalidSmsProviderException;
use OCP\AppFramework\IAppContainer;

class ProviderFactory {

	/** @var IAppContainer */
	private $container;

	public function __construct(IAppContainer $container) {
		$this->container = $container;
	}

	public function getProvider(string $id): IProvider {
		switch ($id) {
			case PuzzelSMS::PROVIDER_ID:
				return $this->container->query(PuzzelSMS::class);
			case PlaySMS::PROVIDER_ID:
				return $this->container->query(PlaySMS::class);
			case WebSms::PROVIDER_ID:
				return $this->container->query(WebSms::class);
			case ClockworkSMS::PROVIDER_ID:
				return $this->container->query(ClockworkSMS::class);
			case EcallSMS::PROVIDER_ID:
				return $this->container->query(EcallSMS::class);
			case VoipMs::PROVIDER_ID:
				return $this->container->query(VoipMs::class);
			case HuaweiE3531::PROVIDER_ID:
				return $this->container->query(HuaweiE3531::class);
            case Sms77Io::PROVIDER_ID:
                return $this->container->query(Sms77Io::class);
			default:
				throw new InvalidSmsProviderException("Provider <$id> does not exist");
		}
	}

}