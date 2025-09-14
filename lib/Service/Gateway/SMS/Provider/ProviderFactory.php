<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Exception\InvalidProviderException;
use Psr\Container\ContainerInterface;

class ProviderFactory {

	public function __construct(
		private ContainerInterface $container,
	) {
	}

	public function getProvider(string $id): IProvider {
		return match ($id) {
			SipGate::PROVIDER_ID => $this->container->get(SipGate::class),
			PuzzelSMS::PROVIDER_ID => $this->container->get(PuzzelSMS::class),
			PlaySMS::PROVIDER_ID => $this->container->get(PlaySMS::class),
			SMSGlobal::PROVIDER_ID => $this->container->get(SMSGlobal::class),
			WebSms::PROVIDER_ID => $this->container->get(WebSms::class),
			ClockworkSMS::PROVIDER_ID => $this->container->get(ClockworkSMS::class),
			EcallSMS::PROVIDER_ID => $this->container->get(EcallSMS::class),
			VoipMs::PROVIDER_ID => $this->container->get(VoipMs::class),
			Voipbuster::PROVIDER_ID => $this->container->get(Voipbuster::class),
			HuaweiE3531::PROVIDER_ID => $this->container->get(HuaweiE3531::class),
			Sms77Io::PROVIDER_ID => $this->container->get(Sms77Io::class),
			Ovh::PROVIDER_ID => $this->container->get(Ovh::class),
			SpryngSMS::PROVIDER_ID => $this->container->get(SpryngSMS::class),
			ClickatellCentral::PROVIDER_ID => $this->container->get(ClickatellCentral::class),
			ClickatellPortal::PROVIDER_ID => $this->container->get(ClickatellPortal::class),
			ClickSend::PROVIDER_ID => $this->container->get(ClickSend::class),
			SerwerSMS::PROVIDER_ID => $this->container->get(SerwerSMS::class),
			SMSApi::PROVIDER_ID => $this->container->get(SMSApi::class),
			default => throw new InvalidProviderException("Provider <$id> does not exist"),
		};
	}
}
