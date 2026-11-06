<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 kwtSMS <support@kwtsms.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Provider\Channel\SMS\Provider\Drivers;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\Drivers\KwtSms;
use OCA\TwoFactorGateway\Provider\FieldType;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

// Numbers use country code 999 (unassigned), so nobody can receive them.
final class KwtSmsTest extends AppTestCase {
	private const ENDPOINT = 'https://www.kwtsms.com/API/send/';

	private IClientService&MockObject $clientService;
	private IClient&MockObject $client;

	protected function setUp(): void {
		parent::setUp();
		$this->client = $this->createMock(IClient::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->clientService->method('newClient')->willReturn($this->client);
	}

	public function testSettings(): void {
		$settings = (new KwtSms($this->clientService))->createSettings();

		$this->assertSame('kwtsms', $settings->id);
		$this->assertSame('kwtSMS', $settings->name);
		$this->assertSame(['username', 'password', 'sender', 'test_mode'], array_map(fn ($f) => $f->field, $settings->fields));
		$this->assertSame(FieldType::SECRET, $settings->fields[1]->type);
		$this->assertTrue($settings->fields[2]->optional);
		$this->assertSame('KWT-SMS', $settings->fields[2]->default);
	}

	public function testSendPostsJsonWithNormalizedNumber(): void {
		$driver = $this->makeConfiguredDriver('ABC-Co');

		$this->client->expects($this->once())
			->method('post')
			->with(
				self::ENDPOINT,
				$this->callback(function (array $opts): bool {
					$this->assertSame('nextcloud_username', $opts['json']['username']);
					$this->assertSame('nextcloud_password', $opts['json']['password']);
					$this->assertSame('ABC-Co', $opts['json']['sender']);
					$this->assertSame('99912345678', $opts['json']['mobile']);
					$this->assertSame('123456 is your Nextcloud verification code', $opts['json']['message']);
					$this->assertFalse($opts['http_errors']);
					return true;
				}),
			)
			->willReturn($this->makeResponse('{"result":"OK","msg-id":"abc","numbers":1,"points-charged":1}'));

		$driver->send('+999 1234 5678', '123456 is your Nextcloud verification code');
	}

	public function testEmptySenderFallsBackToDefault(): void {
		$driver = $this->makeConfiguredDriver('');
		$this->client->expects($this->once())
			->method('post')
			->with(self::ENDPOINT, $this->callback(fn (array $opts): bool => $opts['json']['sender'] === 'KWT-SMS'))
			->willReturn($this->makeResponse('{"result":"OK"}'));

		$driver->send('99912345678', 'hello');
	}

	public static function numberProvider(): array {
		return [
			'plus and spaces' => ['+999 1234 5678', '99912345678'],
			'double zero' => ['0099912345678', '99912345678'],
			'dashes' => ['999-1234-5678', '99912345678'],
			'arabic-indic digits' => ['٩٩٩١٢٣٤٥٦٧٨', '99912345678'],
			'persian digits' => ['۹۹۹۱۲۳۴۵۶۷۸', '99912345678'],
		];
	}

	#[DataProvider('numberProvider')]
	public function testNormalizeNumber(string $input, string $expected): void {
		$this->assertSame($expected, KwtSms::normalizeNumber($input));
	}

	public function testInvalidNumberIsRejectedWithoutARequest(): void {
		$driver = $this->makeConfiguredDriver('KWT-SMS');
		$this->client->expects($this->never())->method('post');

		$this->expectException(MessageTransmissionException::class);
		$driver->send('12345', 'hello');
	}

	public function testGatewayErrorIsReported(): void {
		$driver = $this->makeConfiguredDriver('KWT-SMS');
		$this->client->method('post')->willReturn(
			$this->makeResponse('{"result":"ERROR","code":"ERR003","description":"Authentication error"}'),
		);

		$this->expectException(MessageTransmissionException::class);
		$this->expectExceptionMessage('kwtSMS gateway returned ERR003 Authentication error');
		$driver->send('99912345678', 'hello');
	}

	public function testNonJsonAnswerIsAnError(): void {
		$driver = $this->makeConfiguredDriver('KWT-SMS');
		$this->client->method('post')->willReturn($this->makeResponse('<html>Bad gateway</html>'));

		$this->expectException(MessageTransmissionException::class);
		$driver->send('99912345678', 'hello');
	}

	public function testTransportFailureIsWrapped(): void {
		$driver = $this->makeConfiguredDriver('KWT-SMS');
		$this->client->method('post')->willThrowException(new \RuntimeException('timeout'));

		$this->expectException(MessageTransmissionException::class);
		$this->expectExceptionMessage('Failed to reach the kwtSMS gateway: timeout');
		$driver->send('99912345678', 'hello');
	}

	public function testTestModeAddsTheTestFlag(): void {
		$driver = $this->makeConfiguredDriver('ABC-Co');
		$driver->setTestMode('1');
		$this->client->expects($this->once())
			->method('post')
			->with(self::ENDPOINT, $this->callback(fn (array $opts): bool => ($opts['json']['test'] ?? null) === '1'))
			->willReturn($this->makeResponse('{"result":"OK","msg-id":"m1"}'));
		$driver->send('+999 1234 5678', 'code');
	}

	public function testTestModeAcceptsTheRawYesAnswer(): void {
		$driver = $this->makeConfiguredDriver('ABC-Co');
		$driver->setTestMode('y');
		$this->client->expects($this->once())
			->method('post')
			->with(self::ENDPOINT, $this->callback(fn (array $opts): bool => ($opts['json']['test'] ?? null) === '1'))
			->willReturn($this->makeResponse('{"result":"OK","msg-id":"m1"}'));
		$driver->send('+999 1234 5678', 'code');
	}

	public function testNoTestFlagByDefault(): void {
		$driver = $this->makeConfiguredDriver('ABC-Co');
		$this->client->expects($this->once())
			->method('post')
			->with(self::ENDPOINT, $this->callback(fn (array $opts): bool => !array_key_exists('test', $opts['json'])))
			->willReturn($this->makeResponse('{"result":"OK","msg-id":"m1"}'));
		$driver->send('+999 1234 5678', 'code');
	}

	private function makeConfiguredDriver(string $sender): KwtSms {
		$driver = new KwtSms($this->clientService);
		$driver->setAppConfig($this->makeInMemoryAppConfig());
		$driver->setUsername('nextcloud_username');
		$driver->setPassword('nextcloud_password');
		$driver->setSender($sender);
		return $driver;
	}

	private function makeResponse(string $body): IResponse&MockObject {
		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn($body);
		return $response;
	}
}
