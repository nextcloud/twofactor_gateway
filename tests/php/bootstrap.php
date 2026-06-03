<?php

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\Server;

if (!defined('PHPUNIT_RUN')) {
	define('PHPUNIT_RUN', 1);
}

require_once __DIR__ . '/../../../../lib/base.php';
require_once __DIR__ . '/../../../../tests/autoload.php';

$config = Server::get(IConfig::class);
$appManager = Server::get(IAppManager::class);

if ($config->getAppValue('twofactor_gateway', 'installed_version', '') === '') {
	$config->setAppValue('twofactor_gateway', 'installed_version', '4.0.0-dev.0');
}

if (!in_array('twofactor_gateway', $appManager->getEnabledApps(), true)) {
	$appManager->enableApp('twofactor_gateway', true);
}

$appManager->loadApp('twofactor_gateway');
