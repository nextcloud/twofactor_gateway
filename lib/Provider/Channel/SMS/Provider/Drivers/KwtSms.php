<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 kwtSMS <support@kwtsms.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\Drivers;

use Exception;
use OCA\TwoFactorGateway\Exception\ConfigurationException;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Channel\SMS\Provider\AProvider;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\FieldType;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

/**
 * kwtSMS (Kuwait and the Gulf), JSON API: https://www.kwtsms.com/api-documentation.html
 *
 * @method string getUsername()
 * @method static setUsername(string $username)
 *
 * @method string getPassword()
 * @method static setPassword(string $password)
 *
 * @method string getSender()
 * @method static setSender(string $sender)
 */
class KwtSms extends AProvider {
	private const ENDPOINT = 'https://www.kwtsms.com/API/send/';

	private IClient $client;

	public function __construct(
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	public function createSettings(): Settings {
		return new Settings(
			id: 'kwtsms',
			name: 'kwtSMS',
			fields: [
				new FieldDefinition(
					field: 'username',
					prompt: 'Please enter your kwtSMS API username:',
					helper: 'The API username of your kwtSMS account, not your mobile number',
				),
				new FieldDefinition(
					field: 'password',
					prompt: 'Please enter your kwtSMS API password:',
					type: FieldType::SECRET,
				),
				new FieldDefinition(
					field: 'sender',
					prompt: 'Please enter your kwtSMS Sender ID:',
					default: 'KWT-SMS',
					optional: true,
				),
				new FieldDefinition(
					field: 'test_mode',
					prompt: 'Send in test mode?',
					default: '0',
					optional: true,
					type: FieldType::BOOLEAN,
					helper: 'Test messages are queued on kwtSMS but not delivered; their credits are held until you delete them from the Sending Queue in your kwtSMS account.',
				),
			]
		);
	}

	/**
	 * kwtSMS expects digits only, with the country code and no leading zeros: "+965 9xxx", "00965 9xxx" and
	 * Arabic-Indic digits all become "9659xxx".
	 */
	public static function normalizeNumber(string $identifier): string {
		$latin = strtr($identifier, [
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		]);
		return ltrim((string)preg_replace('/\D/', '', $latin), '0');
	}

	#[\Override]
	public function send(string $identifier, string $message) {
		$number = self::normalizeNumber($identifier);
		if (strlen($number) < 7 || strlen($number) > 15) {
			throw new MessageTransmissionException('kwtSMS: invalid phone number, use the international format');
		}

		try {
			$sender = $this->getSender();
		} catch (ConfigurationException) {
			$sender = '';
		}
		try {
			$testMode = in_array(strtolower(trim((string)$this->getTestMode())), ['1', 'y', 'yes', 'true', 'on'], true);
		} catch (ConfigurationException) {
			$testMode = false;
		}
		$payload = [
			'username' => $this->getUsername(),
			'password' => $this->getPassword(),
			'sender' => $sender !== '' ? $sender : 'KWT-SMS',
			'mobile' => $number,
			'message' => $message,
		];
		if ($testMode) {
			$payload['test'] = '1';
		}
		try {
			$response = $this->client->post(self::ENDPOINT, [
				'headers' => [
					'Accept' => 'application/json',
				],
				'json' => $payload,
				'http_errors' => false,
			]);
		} catch (Exception $ex) {
			throw new MessageTransmissionException(
				'Failed to reach the kwtSMS gateway: ' . $ex->getMessage(),
				0,
				$ex,
			);
		}

		$decoded = json_decode((string)$response->getBody(), true);
		if (!is_array($decoded) || ($decoded['result'] ?? '') !== 'OK') {
			$code = is_array($decoded) ? (string)($decoded['code'] ?? 'unknown') : 'unknown';
			$description = is_array($decoded) ? (string)($decoded['description'] ?? '') : '';
			throw new MessageTransmissionException(
				trim('kwtSMS gateway returned ' . $code . ' ' . $description),
			);
		}
	}
}
