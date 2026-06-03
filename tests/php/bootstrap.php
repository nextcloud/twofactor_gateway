<?php

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCP\App\IAppManager;
use OCP\Server;

if (!defined('PHPUNIT_RUN')) {
	define('PHPUNIT_RUN', 1);
}

require_once __DIR__ . '/../../../../lib/base.php';
require_once __DIR__ . '/../../../../tests/autoload.php';

// Don't load the app here - let makeInMemoryAppConfig handle dependency injection in tests
// Server::get(IAppManager::class)->loadApp('twofactor_gateway');
