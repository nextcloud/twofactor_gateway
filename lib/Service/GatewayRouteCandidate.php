<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service;

use OCA\TwoFactorGateway\Provider\Gateway\IGateway;

/**
 * @psalm-import-type GatewayInstanceArray from GatewayInstanceRecord
 * @psalm-import-type GatewayInstanceInputArray from GatewayInstanceRecord
 */
final class GatewayRouteCandidate {
	public function __construct(
		public IGateway $gateway,
		public string $providerId,
		public string $publicInstanceId,
		public GatewayInstanceRecord $instance,
	) {
	}

	/** @param array{gateway: IGateway, providerId?: string, publicInstanceId?: string, instance: GatewayInstanceInputArray} $candidate */
	public static function fromArray(array $candidate): self {
		return new self(
			gateway: $candidate['gateway'],
			providerId: (string)($candidate['providerId'] ?? $candidate['gateway']->getProviderId()),
			publicInstanceId: (string)($candidate['publicInstanceId'] ?? $candidate['instance']['id']),
			instance: GatewayInstanceRecord::fromArray($candidate['instance']),
		);
	}

	/** @param GatewayInstanceInputArray $instance */
	public static function fromGatewayAndInstance(IGateway $gateway, array $instance, bool $prefixPublicId): self {
		$record = GatewayInstanceRecord::fromArray($instance);

		return new self(
			gateway: $gateway,
			providerId: $gateway->getProviderId(),
			publicInstanceId: $prefixPublicId ? $gateway->getProviderId() . ':' . $record->id : $record->id,
			instance: $record,
		);
	}

	/** @return array{gateway: IGateway, providerId: string, publicInstanceId: string, instance: GatewayInstanceArray} */
	public function toArray(): array {
		return [
			'gateway' => $this->gateway,
			'providerId' => $this->providerId,
			'publicInstanceId' => $this->publicInstanceId,
			'instance' => $this->instance->toArray(),
		];
	}
}
