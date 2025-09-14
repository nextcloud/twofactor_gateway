<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Jordan Bieder <jordan.bieder@geduld.fr>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\AppInfo\Application;
use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

class OvhConfig extends AGatewayConfig {
	protected const expected = [
		'ovh_application_key',
		'ovh_application_secret',
		'ovh_consumer_key',
		'ovh_endpoint',
		'ovh_account',
		'ovh_sender'
	];

	public function getApplicationKey(): string {
		return $this->getOrFail('ovh_application_key');
	}

	public function getApplicationSecret(): string {
		return $this->getOrFail('ovh_application_secret');
	}

	public function getConsumerKey(): string {
		return $this->getOrFail('ovh_consumer_key');
	}

	public function getEndpoint(): string {
		return $this->getOrFail('ovh_endpoint');
	}

	public function getAccount(): string {
		return $this->getOrFail('ovh_account');
	}

	public function getSender(): string {
		return $this->getOrFail('ovh_sender');
	}

	public function setApplicationKey(string $appKey): void {
		$this->config->setValueString(Application::APP_ID, 'ovh_application_key', $appKey);
	}

	public function setApplicationSecret(string $appSecret): void {
		$this->config->setValueString(Application::APP_ID, 'ovh_application_secret', $appSecret);
	}

	public function setConsumerKey(string $consumerKey): void {
		$this->config->setValueString(Application::APP_ID, 'ovh_consumer_key', $consumerKey);
	}

	public function setEndpoint(string $endpoint): void {
		$this->config->setValueString(Application::APP_ID, 'ovh_endpoint', $endpoint);
	}

	public function setAccount($account): void {
		$this->config->setValueString(Application::APP_ID, 'ovh_account', $account);
	}

	public function setSender($sender): void {
		$this->config->setValueString(Application::APP_ID, 'ovh_sender', $sender);
	}
}
