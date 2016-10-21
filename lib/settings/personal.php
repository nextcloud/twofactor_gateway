<?php

/**
 * ownCloud - Richdocuments App
 *
 * @author Blagovest Petrov
 * @copyright 2016 Blagovest Petrov <blagovest@petrovs.info>
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\TwoFactor_Sms;

use \OCA\TwoFactor_Sms\AppInfo\Application;

$app = new Application();
$response = $app->getContainer()->query('\OCA\Richdocuments\Controller\SettingsController')->settingsIndex();
return $response->render();
