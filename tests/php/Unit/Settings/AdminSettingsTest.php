<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Settings;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\GatewayAdminInitialStateService;
use OCA\TwoFactorGateway\Settings\AdminSettings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminSettingsTest extends TestCase {
	private IInitialState&MockObject $initialState;
	private GatewayAdminInitialStateService&MockObject $gatewayAdminInitialStateService;

	protected function setUp(): void {
		parent::setUp();

		$this->initialState = $this->createMock(IInitialState::class);
		$this->gatewayAdminInitialStateService = $this->createMock(GatewayAdminInitialStateService::class);
	}

	#[DataProvider('authorizedKeyProvider')]
	public function testAuthorizedAppConfigPatternsMatchExpectedKeys(string $key, bool $expected): void {
		$settings = $this->createSettings();
		$config = $settings->getAuthorizedAppConfig();
		$patterns = $config[Application::APP_ID] ?? [];

		$matched = false;
		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $key) === 1) {
				$matched = true;
				break;
			}
		}

		$this->assertSame($expected, $matched);
	}

	public function testGetFormProvidesAdminInitialState(): void {
		$snapshot = [
			'gateways' => [
				[
					'id' => 'signal',
					'name' => 'Signal',
					'fields' => [],
					'instances' => [],
				],
			],
			'groups' => [
				['id' => 'admins', 'displayName' => 'Admins'],
			],
		];

		$this->gatewayAdminInitialStateService->expects($this->once())
			->method('build')
			->willReturn($snapshot);

		$this->initialState->expects($this->once())
			->method('provideInitialState')
			->with('admin-settings', $snapshot);

		$response = $this->createSettings()->getForm();

		$this->assertInstanceOf(TemplateResponse::class, $response);
	}

	/**
	 * @return iterable<string, array{0: string, 1: bool}>
	 */
	public static function authorizedKeyProvider(): iterable {
		yield 'allows gateway registry keys' => ['instances:telegram', true];
		yield 'allows per-instance selector field keys' => ['telegram:a1b2c3d4e5f60708:provider', true];
		yield 'allows per-instance secret field keys' => ['telegram:a1b2c3d4e5f60708:api_hash', true];
		yield 'denies per-instance keys with invalid id shape' => ['telegram:abc123:provider', false];
		yield 'denies legacy single-instance provider keys' => ['telegram_provider_name', false];
		yield 'denies operational secret keys with underscores' => ['gowhatsapp_webhook_secret', false];
		yield 'denies deeper colon namespaces' => ['telegram:a1b2c3d4e5f60708:provider:extra', false];
	}

	private function createSettings(): AdminSettings {
		return new AdminSettings(
			$this->initialState,
			$this->gatewayAdminInitialStateService,
		);
	}
}
