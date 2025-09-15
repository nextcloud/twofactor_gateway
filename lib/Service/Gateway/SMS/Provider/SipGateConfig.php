<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use OCA\TwoFactorGateway\Service\Gateway\AGatewayConfig;

/**
 * @method string getTokenId()
 * @method static setTokenId(string $tokenId)
 * @method string getAccessToken()
 * @method static setAccessToken(string $accessToken)
 * @method string getWebSmsExtension()
 * @method static setWebSmsExtension(string $webSmsExtension)
 */
class SipGateConfig extends AGatewayConfig {
	public const SCHEMA = [
		'id' => 'sipgate',
		'name' => 'SipGate',
		'fields' => [
			['field' => 'token_id',        'prompt' => 'Please enter your sipgate token-id:'],
			['field' => 'access_token',    'prompt' => 'Please enter your sipgate access token:'],
			['field' => 'web_sms_extension','prompt' => 'Please enter your sipgate web-sms extension:'],
		],
	];
}
