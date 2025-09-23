<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Signal;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * An integration of https://gitlab.com/morph027/signal-web-gateway
 *
 * @method string getUrl()
 * @method AGateway setUrl(string $url)
 * @method string getAccount()
 * @method AGateway setAccount(string $account)
 */
class Gateway extends AGateway {
	public const SCHEMA = [
		'name' => 'Signal',
		'instructions' => 'The gateway can send authentication to your Signal mobile and deskop app.',
		'fields' => [
			['field' => 'url', 'prompt' => 'Please enter the URL of the Signal gateway (leave blank to use default):', 'default' => 'http://localhost:5000'],
			['field' => 'account', 'prompt' => 'Please enter the account (phone-number) of the sending signal account (leave blank if a phone-number is not required):', 'default' => ''],
		],
	];
	public const ACCOUNT_UNNECESSARY = 'unneccessary';

	public function __construct(
		public IAppConfig $appConfig,
		private IClientService $clientService,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
		parent::__construct($appConfig);
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		$client = $this->clientService->newClient();
		// determine type of gateway

		// test for native signal-cli JSON RPC.
		$response = $client->post(
			$this->getUrl() . '/api/v1/rpc',
			[
				'http_errors' => false,
				'json' => [
					'jsonrpc' => '2.0',
					'method' => 'version',
					'id' => 'version_' . $this->timeFactory->getTime(),
				],
			]);
		if ($response->getStatusCode() === 200 || $response->getStatusCode() === 201) {
			// native signal-cli JSON RPC.

			// Groups have to be detected and passed with the "group-id" parameter. We assume a group is given as base64 encoded string
			$groupId = base64_decode($identifier, strict: true);
			$isGroup = $groupId !== false && base64_encode($groupId) === $identifier;
			$recipientKey = $isGroup ? 'group-id' : 'recipient';
			$params = [
				'message' => $message,
				$recipientKey => $identifier,
				'account' => $this->getAccount(), // mandatory for native RPC API
			];
			$response = $response = $client->post(
				$this->getUrl() . '/api/v1/rpc',
				[
					'json' => [
						'jsonrpc' => '2.0',
						'method' => 'send',
						'id' => 'code_' . $this->timeFactory->getTime(),
						'params' => $params,
					],
				]);
			$body = $response->getBody();
			$json = json_decode($body, true);
			$statusCode = $response->getStatusCode();
			// The 201 "created" is probably a bug.
			if ($statusCode < 200 || $statusCode >= 300 || is_null($json) || !is_array($json) || ($json['jsonrpc'] ?? null) != '2.0' || !isset($json['result']['timestamp'])) {
				throw new MessageTransmissionException("error reported by Signal gateway, status=$statusCode, body=$body}");
			}
		} else {
			// Try gateway in the style of https://gitlab.com/morph027/signal-cli-dbus-rest-api
			$response = $client->get($this->getUrl() . '/v1/about');
			if ($response->getStatusCode() === 200) {
				// Not so "ńew style" gateway, see
				// https://gitlab.com/morph027/signal-cli-dbus-rest-api
				// https://gitlab.com/morph027/python-signal-cli-rest-api
				// https://github.com/bbernhard/signal-cli-rest-api
				$body = $response->getBody();
				$json = json_decode($body, true);
				$versions = $json['versions'] ?? [];
				if (is_array($versions) && in_array('v2', $versions)) {
					$json = [
						'recipients' => $identifier,
						'message' => $message,
					];
					$account = $this->getAccount();
					if ($account != self::ACCOUNT_UNNECESSARY) {
						$json['account'] = $account;
					}
					$response = $client->post(
						$this->getUrl() . '/v2/send',
						[
							'json' => $json,
						]
					);
				} else {
					$response = $client->post(
						$this->getUrl() . '/v1/send/' . $identifier,
						[
							'json' => [ 'message' => $message ],
						]
					);
				}
				$body = (string)$response->getBody();
				$json = json_decode($body, true);
				$status = $response->getStatusCode();
				if ($status !== 201 || is_null($json) || !is_array($json) || !isset($json['timestamp'])) {
					throw new MessageTransmissionException("error reported by Signal gateway, status=$status, body=$body}");
				}
			} else {
				// Try old deprecated gateway https://gitlab.com/morph027/signal-web-gateway
				$response = $client->post(
					$this->getUrl() . '/v1/send/' . $identifier,
					[
						'body' => [
							'to' => $identifier,
							'message' => $message,
						],
						'json' => [ 'message' => $message ],
					]
				);
				$body = (string)$response->getBody();
				$json = json_decode($body, true);

				$status = $response->getStatusCode();
				if ($status !== 200 || is_null($json) || !is_array($json) || !isset($json['success']) || $json['success'] !== true) {
					throw new MessageTransmissionException("error reported by Signal gateway, status=$status, body=$body}");
				}
			}
		}
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$helper = new QuestionHelper();
		$urlQuestion = new Question(self::SCHEMA['fields'][0]['prompt'], self::SCHEMA['fields'][0]['default']);
		$url = $helper->ask($input, $output, $urlQuestion);
		$output->writeln("Using $url.");

		$this->setUrl($url);

		$accountQuestion = new Question(self::SCHEMA['fields'][1]['prompt'], self::SCHEMA['fields'][1]['default']);
		$account = $helper->ask($input, $output, $accountQuestion);
		if ($account == '') {
			$account = self::ACCOUNT_UNNECESSARY;
			$output->writeln('A signal account is not needed, assuming it is hardcoded into the signal gateway server.');
		} else {
			$output->writeln("Using $account.");
		}

		$this->setAccount($account);

		return 0;
	}
}
