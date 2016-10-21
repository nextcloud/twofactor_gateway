<?php

namespace OCA\TwoFactor_Sms;

$application = new \OCA\TwoFactor_Sms\AppInfo\Application();
$application->registerRoutes($this, [
	'routes' => [
    ['name' => 'settings#saveSettings', 'url' => 'ajax/personal.php', 'verb' => 'POST'],
	]
]);
