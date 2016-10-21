<?php

namespace OCA\TwoFactor_Sms\AppInfo;

use \OCP\AppFramework\App;

use \OCA\TwoFactor_Sms\Controller\SettingsController;
use \OCA\TwoFactor_Sms\AppConfig;

class Application extends App {
	public function __construct (array $urlParams = array()) {
		parent::__construct('richdocuments', $urlParams);

		$container = $this->getContainer();

		/**
		 * Controllers
		 */
		$container->registerService('SettingsController', function($c) {
			return new SettingsController(
				$c->query('AppName'),
				$c->query('L10N'),
				$c->query('AppConfig'),
				$c->query('UserId')
			);
		});

		$container->registerService('AppConfig', function($c) {
			return new AppConfig(
				$c->query('CoreConfig')
			);
		});

		/**
		 * Core
		 */
		$container->registerService('CoreConfig', function($c) {
			return $c->query('ServerContainer')->getConfig();
		});
		$container->registerService('L10N', function($c) {
			return $c->query('ServerContainer')->getL10N($c->query('AppName'));
		});
		$container->registerService('UserId', function($c) {
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? '' : $user->getUID();
			return $uid;
		});
	}
}
