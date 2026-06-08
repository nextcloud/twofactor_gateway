<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\AppInfo;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Notification\Notifier;
use OCA\TwoFactorGateway\Provider\Factory;
use OCA\TwoFactorGateway\Provider\Gateway\BootstrapFactory;
use OCA\TwoFactorGateway\Provider\Gateway\IGatewayBootstrap;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class ApplicationTest extends AppTestCase {
	public function testRegisterPublishesNotifierAndProviders(): void {
		$context = $this->createMock(IRegistrationContext::class);
		$bootstrapFactory = $this->createMock(BootstrapFactory::class);
		$gatewayBootstrap = $this->createMock(IGatewayBootstrap::class);
		$providerFactory = $this->createMock(Factory::class);

		$context->expects($this->once())
			->method('registerNotifierService')
			->with(Notifier::class);

		$gatewayBootstrap->expects($this->once())
			->method('register')
			->with($context);

		$providerFactory->method('getFqcnList')->willReturn([
			DummyTwoFactorProvider::class,
		]);

		$context->expects($this->once())
			->method('registerTwoFactorProvider')
			->with(DummyTwoFactorProvider::class);

		$bootstrapFactory->method('getInstances')->willReturn([$gatewayBootstrap]);

		$app = new class($bootstrapFactory, $providerFactory) extends Application {
			public function __construct(
				private BootstrapFactory $bootstrapFactory,
				private Factory $providerFactory,
			) {
				parent::__construct();
			}

			protected function getBootstrapFactory(): BootstrapFactory {
				return $this->bootstrapFactory;
			}

			protected function getProviderFactory(): Factory {
				return $this->providerFactory;
			}
		};

		$app->register($context);
	}
}

class DummyTwoFactorProvider {
}
