<?php

namespace OCA\TwoFactor_Sms;

use \OCP\IConfig;

 class AppConfig{
 	private $appName = 'twofactor_sms';
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * Get a value by key for a user
	 * @param string $userId
	 * @param string $key
	 * @return string
	 */
	public function getUserValue($userId, $key) {
		$defaultValue = null;
		if (array_key_exists($key, $this->defaults)){
			$defaultValue = $this->defaults[$key];
		}
		return $this->config->getUserValue($userId, $this->appName, $key, $defaultValue);
	}

	/**
	 * Set a value by key for a user
	 * @param string $userId
	 * @param string $key
	 * @param string $value
	 * @return string
	 */
	public function setUserValue($userId, $key, $value) {
		return $this->config->setAppValue($userId, $this->appName, $key, $value);
	}
 }
