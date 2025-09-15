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
	protected const FIELDS = [
		'application_key',
		'application_secret',
		'consumer_key',
		'endpoint',
		'account',
		'sender'
	];

	#[\Override]
	public static function providerId(): string {
		return 'ovh';
	}
}
