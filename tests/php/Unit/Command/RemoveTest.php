<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Command;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Factory as SMSFactory;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Factory as TelegramFactory;
use OCA\TwoFactorGateway\Provider\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\IAppConfig;
use OCP\Server;
use Symfony\Component\Console\Input\ArrayInput;

class RemoveTest extends AppTestCase {

	public function testWithInvalidProvider(): void {
		$this->makeInMemoryAppConfig();

		/** @var \OC\Console\Application */
		$application = Server::get(\OC\Console\Application::class);
		$input = new ArrayInput(['twofactorauth:gateway:remove']);
		$input->setStream(self::createStream(['99999']));
		$output = new ConsoleOutputSpy();
		$application->loadCommands($input, $output);
		$application->setAutoExit(false);

		$exitCode = $application->run($input, $output);

		$this->assertSame(1, $exitCode);
	}

	private function configureProviderFields(IAppConfig $appConfig, object $settings, string $nsHint): array {
		$configured = [];
		$this->assertNotEmpty($settings->fields, "Provider {$settings->name} ({$nsHint}) não definiu fields.");
		foreach ($settings->fields as $field) {
			$key = $settings->id . '_' . $field->field;
			$appConfig->setValueString(Application::APP_ID, $key, 'some_value');
			$this->assertArrayHasKey($key, self::$store[Application::APP_ID] ?? [], "Field {$key} não foi salvo ({$nsHint}).");
			$configured[] = $key;
		}
		return $configured;
	}

	private function configureAllChannelProviders(IAppConfig $appConfig, iterable $fqcnList, callable $resolver): array {
		$all = [];
		foreach ($fqcnList as $fqcn) {
			$provider = $resolver($fqcn);
			$settings = $provider->getSettings();
			$all = array_merge($all, $this->configureProviderFields($appConfig, $settings, $fqcn));
		}
		return $all;
	}

	private function runRemoveCommand(\OC\Console\Application $application, string $gatewayId): void {
		$gwFactory = new GatewayFactory();
		$index = 0;
		foreach ($gwFactory->getFqcnList() as $i => $fqcn) {
			$gw = $gwFactory->get($fqcn);
			if ($gw->getProviderId() === $gatewayId) {
				$index = $i;
				break;
			}
		}

		$input = new ArrayInput(['twofactorauth:gateway:remove']);
		$input->setStream(self::createStream([(string)$index]));
		$output = new ConsoleOutputSpy();

		$application->loadCommands($input, $output);
		$application->setAutoExit(false);

		$exit = $application->run($input, $output);
		$this->assertSame(0, $exit, 'Comando de remoção retornou código diferente de 0.');
	}

	private function assertKeysRemoved(array $keys, string $nsHint): void {
		foreach ($keys as $key) {
			$this->assertArrayNotHasKey($key, self::$store[Application::APP_ID] ?? [], "Ainda existe {$key} após remoção ({$nsHint}).");
		}
	}

	private function configureGenericGateway(IAppConfig $appConfig, string $gatewayFqcn): array {
		$gateway = (new GatewayFactory())->get($gatewayFqcn);
		$settings = $gateway->getSettings();
		return $this->configureProviderFields($appConfig, $settings, $gatewayFqcn);
	}

	public function testSmsGatewaysAreConfiguredAndRemoved(): void {
		$this->makeInMemoryAppConfig();
		/** @var IAppConfig $appConfig */
		$appConfig = Server::get(IAppConfig::class);
		/** @var \OC\Console\Application $application */
		$application = Server::get(\OC\Console\Application::class);

		$smsFactory = new SMSFactory();
		$configured = $this->configureAllChannelProviders(
			$appConfig,
			$smsFactory->getFqcnList(),
			fn (string $fqcn) => $smsFactory->get($fqcn),
		);

		$this->runRemoveCommand($application, 'sms');
		$this->assertKeysRemoved($configured, 'sms');
	}

	public function testTelegramGatewaysAreConfiguredAndRemoved(): void {
		$this->makeInMemoryAppConfig();
		$appConfig = Server::get(IAppConfig::class);
		$application = Server::get(\OC\Console\Application::class);

		$tgFactory = new TelegramFactory();
		$configured = $this->configureAllChannelProviders(
			$appConfig,
			$tgFactory->getFqcnList(),
			fn (string $fqcn) => $tgFactory->get($fqcn),
		);

		$this->runRemoveCommand($application, 'telegram');
		$this->assertKeysRemoved($configured, 'telegram');
	}

	public function testOtherGatewaysAreConfiguredAndRemoved(): void {
		$this->makeInMemoryAppConfig();
		$appConfig = Server::get(IAppConfig::class);
		$application = Server::get(\OC\Console\Application::class);

		$gwFactory = new GatewayFactory();

		foreach ($gwFactory->getFqcnList() as $fqcn) {
			$gateway = $gwFactory->get($fqcn);
			$id = $gateway->getProviderId();

			if (in_array($id, ['sms', 'telegram', 'gowhatsapp'], true)) {
				continue;
			}

			$configured = $this->configureGenericGateway($appConfig, $fqcn);
			$this->runRemoveCommand($application, $id);
			$this->assertKeysRemoved($configured, $fqcn);
		}
	}
}
