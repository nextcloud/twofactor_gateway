<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Tests\Unit\Command;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Tests\Unit\AppTestCase;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\Server;

abstract class ConsoleCommandTestCase extends AppTestCase {
	private static bool $appWasEnabled;
	private static string $originalInstalledVersion;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$appManager = Server::get(IAppManager::class);
		$config = Server::get(IConfig::class);

		self::$originalInstalledVersion = $config->getAppValue(Application::APP_ID, 'installed_version', '');
		if (self::$originalInstalledVersion === '') {
			$config->setAppValue(
				Application::APP_ID,
				'installed_version',
				$appManager->getAppVersion(Application::APP_ID, false),
			);
		}

		self::$appWasEnabled = $appManager->isEnabledForAnyone(Application::APP_ID);
		if (!self::$appWasEnabled) {
			$appManager->enableApp(Application::APP_ID);
		}

		$appManager->loadApp(Application::APP_ID);
	}

	public static function tearDownAfterClass(): void {
		$appManager = Server::get(IAppManager::class);
		$config = Server::get(IConfig::class);

		if (!self::$appWasEnabled) {
			$appManager->disableApp(Application::APP_ID);
		}

		if (self::$originalInstalledVersion === '') {
			$config->deleteAppValue(Application::APP_ID, 'installed_version');
		}

		parent::tearDownAfterClass();
	}
}
