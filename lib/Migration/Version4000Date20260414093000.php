<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Migration;

use Closure;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Service\GatewayConfigService;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version4000Date20260414093000 extends SimpleMigrationStep {
	public function __construct(
		private GatewayFactory $gatewayFactory,
		private GatewayConfigService $gatewayConfigService,
	) {
	}

	#[\Override]
	public function name(): string {
		return 'Create default gateway instances from primary configuration';
	}

	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		foreach ($this->gatewayFactory->getFqcnList() as $fqcn) {
			$gateway = $this->gatewayFactory->get($fqcn);
			if (!$this->gatewayConfigService->createDefaultInstanceFromPrimaryConfiguration($gateway)) {
				continue;
			}

			$output->info('Created default gateway instance from primary configuration for ' . $gateway->getProviderId());
		}
	}
}
