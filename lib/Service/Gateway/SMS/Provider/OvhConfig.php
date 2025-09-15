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
 * @method $this setApplicationKey(string $applicationKey)
 * @method string getApplicationSecret()
 * @method $this setApplicationSecret(string $applicationSecret)
 * @method string getConsumerKey()
 * @method $this setConsumerKey(string $consumerKey)
 * @method string getEndpoint()
 * @method $this setEndpoint(string $endpoint)
 * @method string getAccount()
 * @method $this setAccount(string $account)
 * @method string getSender()
 * @method $this setSender(string $sender)
 */
class OvhConfig extends AGatewayConfig {
	protected const expected = [
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
