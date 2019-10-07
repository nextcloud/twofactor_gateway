<?php

declare(strict_types=1);

namespace OCA\TwoFactorGateway\Service\Gateway\SMS\Provider;

use Exception;
use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;

class AndroidGsmModem implements IProvider {

	const PROVIDER_ID = 'android_gsm_modem';

	/** @var IClient */
	private $client;

	/** @var AndroidGsmModemConfig */
	private $config;

	public function __construct(IClientService $clientService,
								AndroidGsmModemConfig $config) {
		$this->client = $clientService->newClient();
		$this->config = $config;
	}

	/**
	 * @param string $identifier
	 * @param string $message
	 *
	 * @throws SmsTransmissionException
	 */
	public function send(string $identifier, string $message) {
		$config = $this->getConfig();
		$user = $config->getUser();
		$password = $config->getPassword();
		$host = $config->getHost();
		$protocol = $config->getProtocol();
		try {
			$this->client->get("$protocol://$host/SendSMS", [
				'query' => [
					'user' => $user,
					'password' => $password,
					'phone' => $identifier,
					'message' => $message,
				],
			]);
		} catch (Exception $ex) {
			throw new SmsTransmissionException();
		}
	}

	/**
	 * @return AndroidGsmModemConfig
	 */
	public function getConfig(): IProviderConfig {
		return $this->config;
	}
}
