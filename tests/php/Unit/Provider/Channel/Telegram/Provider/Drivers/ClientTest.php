<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\Telegram\Provider\Drivers;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\Client;
use OCP\Files\IAppData;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ClientTest extends TestCase {
	private LoggerInterface&MockObject $logger;
	private IL10N&MockObject $l10n;
	private IAppData&MockObject $appData;
	private IConfig&MockObject $config;

	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(LoggerInterface::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->appData = $this->createMock(IAppData::class);
		$this->config = $this->createMock(IConfig::class);
	}

	public function testCreateSettingsSeparatesPromptFromHelperText(): void {
		$provider = new Client($this->logger, $this->l10n, $this->appData, $this->config);
		$settings = $provider->createSettings();

		$this->assertSame('Please enter your Telegram api_id:', $settings->fields[0]->prompt);
		$this->assertSame('Get one at https://my.telegram.org/apps', $settings->fields[0]->helper);
		$this->assertSame('Please enter your Telegram api_hash:', $settings->fields[1]->prompt);
		$this->assertSame('Get one at https://my.telegram.org/apps', $settings->fields[1]->helper);
	}

	public function testSendReturnsHelpfulMessageWhenSessionIsNotLoggedIn(): void {
		/** @var Client&object{cliOutput:list<string>, cliExitCode:int} $provider */
		$provider = $this->createTestableClient()
			->withRuntimeConfig([
				'api_id' => '18307',
				'api_hash' => 'secret-hash',
			]);
		$provider->cliOutput = [
			'Open Telegram on your phone, go to Settings > Devices > Link Desktop Device and scan the above QR code to login automatically.',
			'Alternatively, you can also enter a bot token or phone number to login manually:',
		];
		$provider->cliExitCode = 1;

		$this->expectException(MessageTransmissionException::class);
		$this->expectExceptionMessage('Telegram Client session is not logged in. Complete the Telegram login flow for this gateway before testing or sending messages.');

		try {
			$provider->send('vitormattos', 'Test');
		} catch (MessageTransmissionException $e) {
			$this->assertStringNotContainsString('secret-hash', $e->getMessage());
			throw $e;
		}
	}

	public function testEnrichTestResultReturnsAccountInfoFromCliJsonOutput(): void {
		/** @var Client&object{cliOutput:list<string>, cliExitCode:int} $provider */
		$provider = $this->createTestableClient()
			->withRuntimeConfig([
				'api_id' => '18307',
				'api_hash' => 'secret-hash',
			]);
		$provider->cliOutput = [
			'Logger: MadelineProto 8.6.2',
			'{"account_name":"Alice Cooper","account_avatar_url":"data:image/png;base64,YXZhdGFy"}',
		];
		$provider->cliExitCode = 0;

		$this->assertSame([
			'account_name' => 'Alice Cooper',
		], $provider->enrichTestResult([], 'vitormattos'));
	}

	public function testCliConfigurePromptsForCredentialsAndStartsLoginFlow(): void {
		/** @var Client&object{interactiveCommand:string, cliExitCode:int} $provider */
		$provider = $this->createTestableClient()->withRuntimeConfig([]);
		$provider->cliExitCode = 0;

		$input = new StringInput('');
		$stream = fopen('php://memory', 'r+');
		fwrite($stream, "18307\nsecret-hash\n");
		rewind($stream);
		$input->setStream($stream);
		$output = new BufferedOutput();

		$exitCode = $provider->cliConfigure($input, $output);

		$this->assertSame(0, $exitCode);
		$this->assertStringContainsString('telegram:login', $provider->interactiveCommand);
		$this->assertStringContainsString('Starting the Telegram login flow', $output->fetch());
	}

	private function createTestableClient(): Client {
		return new class($this->logger, $this->l10n, $this->appData, $this->config) extends Client {
			/** @var list<string> */
			public array $cliOutput = [];
			public int $cliExitCode = 0;
			public string $sessionDirectory = '/tmp/session.madeline';
			public string $interactiveCommand = '';

			protected function executeCliCommand(string $command, array &$output, ?int &$returnVar = null, int $timeoutSeconds = 8): void {
				$output = $this->cliOutput;
				$returnVar = $this->cliExitCode;
			}

			protected function executeInteractiveCliCommand(string $command, ?int &$returnVar = null): void {
				$this->interactiveCommand = $command;
				$returnVar = $this->cliExitCode;
			}

			protected function getSessionDirectory(): string {
				return $this->sessionDirectory;
			}
		};
	}
}
