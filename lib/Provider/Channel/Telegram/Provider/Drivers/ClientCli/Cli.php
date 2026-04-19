#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** Internal Telegram Client CLI bridge used by the native admin setup flow. */
require __DIR__ . '/../../../../../../../vendor-bin/telegram-client/vendor/autoload.php';
require __DIR__ . '/Complete2faLogin.php';
require __DIR__ . '/GetAccountInfo.php';
require __DIR__ . '/GetLoginQr.php';
require __DIR__ . '/Login.php';
require __DIR__ . '/ResetLogin.php';
require __DIR__ . '/SendMessage.php';

use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientCli\Complete2faLogin;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientCli\GetAccountInfo;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientCli\GetLoginQr;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientCli\Login;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientCli\ResetLogin;
use OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientCli\SendMessage;
use Symfony\Component\Console\Application;

$application = new Application('Telegram CLI');
$application->addCommand(new Complete2faLogin());
$application->addCommand(new GetAccountInfo());
$application->addCommand(new GetLoginQr());
$application->addCommand(new Login());
$application->addCommand(new ResetLogin());
$application->addCommand(new SendMessage());
$application->run();
