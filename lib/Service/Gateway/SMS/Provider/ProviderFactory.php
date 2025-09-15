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
			SipGateConfig::providerId() => $this->container->get(SipGate::class),
			PuzzelSMSConfig::providerId() => $this->container->get(PuzzelSMS::class),
			PlaySMSConfig::providerId() => $this->container->get(PlaySMS::class),
			SMSGlobalConfig::providerId() => $this->container->get(SMSGlobal::class),
			WebSmsConfig::providerId() => $this->container->get(WebSms::class),
			ClockworkSMSConfig::providerId() => $this->container->get(ClockworkSMS::class),
			EcallSMSConfig::providerId() => $this->container->get(EcallSMS::class),
			VoipMsConfig::providerId() => $this->container->get(VoipMs::class),
			VoipbusterConfig::providerId() => $this->container->get(Voipbuster::class),
			HuaweiE3531Config::providerId() => $this->container->get(HuaweiE3531::class),
			Sms77IoConfig::providerId() => $this->container->get(Sms77Io::class),
			OvhConfig::providerId() => $this->container->get(Ovh::class),
			SpryngSMSConfig::providerId() => $this->container->get(SpryngSMS::class),
			ClickatellCentralConfig::providerId() => $this->container->get(ClickatellCentral::class),
			ClickatellPortalConfig::providerId() => $this->container->get(ClickatellPortal::class),
			ClickSendConfig::providerId() => $this->container->get(ClickSend::class),
			SerwerSMSConfig::providerId() => $this->container->get(SerwerSMS::class),
			SMSApiConfig::providerId() => $this->container->get(SMSApi::class),
			default => throw new InvalidProviderException("Provider <$id> does not exist"),
		};
	}
}
