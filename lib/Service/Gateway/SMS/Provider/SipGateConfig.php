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
 * @method $this setTokenId(string $tokenId)
 * @method string getAccessToken()
 * @method $this setAccessToken(string $accessToken)
 * @method string getWebSmsExtension()
 * @method $this setWebSmsExtension(string $webSmsExtension)
 */
class SipGateConfig extends AGatewayConfig {
	protected const FIELDS = [
		'token_id',
		'access_token',
		'web_sms_extension',
	];

	#[\Override]
	public static function providerId(): string {
		return 'sipgate';
	}
}
