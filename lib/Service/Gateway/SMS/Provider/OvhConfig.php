<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Jordan Bieder <jordan.bieder@geduld.fr>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getApplicationKey()
 * @method static setApplicationKey(string $applicationKey)
 * @method string getApplicationSecret()
 * @method static setApplicationSecret(string $applicationSecret)
 * @method string getConsumerKey()
 * @method static setConsumerKey(string $consumerKey)
 * @method string getEndpoint()
 * @method static setEndpoint(string $endpoint)
 * @method string getAccount()
 * @method static setAccount(string $account)
 * @method string getSender()
 * @method static setSender(string $sender)
 */
class OvhConfig extends AGatewayConfig {
	public const SMS_SCHEMA = [
		'id' => 'ovh',
		'name' => 'OVH',
		'fields' => [
			['field' => 'endpoint',        'prompt' => 'Please enter the endpoint (ovh-eu, ovh-us, ovh-ca, soyoustart-eu, soyoustart-ca, kimsufi-eu, kimsufi-ca, runabove-ca):'],
			['field' => 'application_key', 'prompt' => 'Please enter your application key:'],
			['field' => 'application_secret','prompt' => 'Please enter your application secret:'],
			['field' => 'consumer_key',    'prompt' => 'Please enter your consumer key:'],
			['field' => 'account',         'prompt' => 'Please enter your account (sms-*****):'],
			['field' => 'sender',          'prompt' => 'Please enter your sender:'],
		],
	];
}
