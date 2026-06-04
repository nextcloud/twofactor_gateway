<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Service\Support;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\IAppConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RuntimeAwareGatewayDouble extends AGateway {
	/** @var list<string> */
	public static array $sentBaseUrls = [];

	public function __construct(
		IAppConfig $appConfig,
		private string $gatewayId = 'runtimeaware',
	) {
		parent::__construct($appConfig);
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		$baseUrl = $this->getBaseUrl();
		self::$sentBaseUrls[] = $baseUrl;
		if (str_contains($baseUrl, 'fail')) {
			throw new MessageTransmissionException('simulated failure');
		}
	}

	#[\Override]
	public function createSettings(): Settings {
		return new Settings(
			name: 'RuntimeAware',
			id: $this->gatewayId,
			fields: [new FieldDefinition('base_url', 'Base URL')],
		);
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		return 0;
	}

	#[\Override]
	public function getProviderId(): string {
		return $this->gatewayId;
	}
}
