<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
			case SipGate::PROVIDER_ID:
				return $this->container->query(SipGate::class);
			case PuzzelSMS::PROVIDER_ID:
				return $this->container->query(PuzzelSMS::class);
			case PlaySMS::PROVIDER_ID:
				return $this->container->query(PlaySMS::class);
			case SMSGlobal::PROVIDER_ID:
				return $this->container->query(SMSGlobal::class);
			case WebSms::PROVIDER_ID:
				return $this->container->query(WebSms::class);
			case ClockworkSMS::PROVIDER_ID:
				return $this->container->query(ClockworkSMS::class);
			case EcallSMS::PROVIDER_ID:
				return $this->container->query(EcallSMS::class);
			case VoipMs::PROVIDER_ID:
				return $this->container->query(VoipMs::class);
			case Voipbuster::PROVIDER_ID:
				return $this->container->query(Voipbuster::class);
			case HuaweiE3531::PROVIDER_ID:
				return $this->container->query(HuaweiE3531::class);
			case Sms77Io::PROVIDER_ID:
				return $this->container->query(Sms77Io::class);
			case Ovh::PROVIDER_ID:
				return $this->container->query(Ovh::class);
			case SpryngSMS::PROVIDER_ID:
				return $this->container->query(SpryngSMS::class);
			case ClickatellCentral::PROVIDER_ID:
				return $this->container->query(ClickatellCentral::class);
			case ClickatellPortal::PROVIDER_ID:
				return $this->container->query(ClickatellPortal::class);
			case ClickSend::PROVIDER_ID:
				return $this->container->query(ClickSend::class);
			case SerwerSMS::PROVIDER_ID:
				return $this->container->query(SerwerSMS::class);
			case SMSApi::PROVIDER_ID:
				return $this->container->query(SMSApi::class);
			default:
				throw new InvalidSmsProviderException("Provider <$id> does not exist");
		}
	}
}
