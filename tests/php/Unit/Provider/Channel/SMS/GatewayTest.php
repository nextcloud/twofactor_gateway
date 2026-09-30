<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\SMS;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Factory;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Gateway;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\Drivers\Sms77Io;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class GatewayTest extends AppTestCase {
	public function testCliConfigurePersistsSelectedProvider(): void {
		$appConfig = $this->makeInMemoryAppConfig();

		$client = $this->createMock(IClient::class);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$provider = new Sms77Io($clientService);
		$provider->setAppConfig($appConfig);

		/** @var Factory&MockObject $factory */
		$factory = $this->createMock(Factory::class);
		$factory->method('getFqcnList')->willReturn([Sms77Io::class]);
		$factory->method('get')->with(Sms77Io::class)->willReturn($provider);

		$input = new ArrayInput([]);
		$stream = fopen('php://memory', 'r+');
		$this->assertIsResource($stream);
		fwrite($stream, "0\nsecret-key\n");
		rewind($stream);
		$input->setStream($stream);

		$output = new BufferedOutput();
		$gateway = new Gateway($appConfig, $factory);

		$this->assertSame(0, $gateway->cliConfigure($input, $output));
		$this->assertSame(
			'sms77io',
			$appConfig->getValueString(Application::APP_ID, 'sms_provider_name'),
		);
		$this->assertSame(
			'secret-key',
			$appConfig->getValueString(Application::APP_ID, 'sms77io_api_key'),
		);
	}
}
