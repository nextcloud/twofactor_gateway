<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use Behat\Behat\Hook\Scope\BeforeSuiteScope;
use Libresign\NextcloudBehat\NextcloudApiContext;

class FeatureContext extends NextcloudApiContext {
	#[BeforeSuite()]
	public static function beforeSuite(BeforeSuiteScope $scope): void {
		parent::beforeSuite($scope);
		self::runCommand('config:system:set debug --value true --type boolean');
	}

	#[BeforeScenario()]
	public static function beforeScenario(): void {
		parent::beforeScenario();
		// Clear all gateway instances between scenarios to guarantee isolation
		self::runCommand('app:config:delete twofactor_gateway --all');
	}
}
