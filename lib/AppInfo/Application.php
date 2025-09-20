<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\AppInfo;

use OCA\TwoFactorGateway\Provider\Factory;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Server;

class Application extends App implements IBootstrap {
	public const APP_ID = 'twofactor_gateway';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	#[\Override]
	public function register(IRegistrationContext $context): void {
		$providerFactory = Server::get(Factory::class);
		$fqcn = $providerFactory->getFqcnList();
		foreach ($fqcn as $class) {
			$context->registerTwoFactorProvider($class);
		}
	}

	#[\Override]
	public function boot(IBootContext $context): void {
	}
}
