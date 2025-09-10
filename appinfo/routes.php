<?php

/**
 * SPDX-FileCopyrightText: 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		[
			'name' => 'settings#getVerificationState',
			'url' => '/settings/{gateway}/verification',
			'verb' => 'GET'
		],
		[
			'name' => 'settings#startVerification',
			'url' => '/settings/{gateway}/verification/start',
			'verb' => 'POST'
		],
		[
			'name' => 'settings#finishVerification',
			'url' => '/settings/{gateway}/verification/finish',
			'verb' => 'POST'
		],
		[
			'name' => 'settings#revokeVerification',
			'url' => '/settings/{gateway}/verification',
			'verb' => 'DELETE'
		],
	]
];
