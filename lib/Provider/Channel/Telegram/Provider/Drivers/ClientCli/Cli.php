#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require __DIR__ . '/../../../../../../../vendor-bin/telegram-client/vendor/autoload.php';
require __DIR__ . '/Login.php';
require __DIR__ . '/SendMessage.php';

use Symfony\Component\Console\Application;

$application = new Application('Telegram CLI');
$application->add(new Login());
$application->add(new SendMessage());
$application->run();
