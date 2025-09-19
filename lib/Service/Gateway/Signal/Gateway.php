<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\Signal;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Service\Gateway\AGateway;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IUser;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * An integration of https://gitlab.com/morph027/signal-web-gateway
 *
 * @method string getUrl()
 * @method static setUrl(string $url)
 */
class Gateway extends AGateway {
	public const SCHEMA = [
		'name' => 'Signal',
		'fields' => [
			['field' => 'url', 'prompt' => 'Please enter the URL of the Signal gateway (leave blank to use default):', 'default' => 'http://localhost:5000'],
		],
	];

	public function __construct(
		public IAppConfig $appConfig,
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appConfig);
	}

	#[\Override]
	public function send(IUser $user, string $identifier, string $message, array $extra = []): void {
		$client = $this->clientService->newClient();
		// determine type of gateway
		$response = $client->get($this->getUrl() . '/v1/about');
		if ($response->getStatusCode() === 200) {
			// New style gateway https://gitlab.com/morph027/signal-cli-dbus-rest-api
			$response = $client->post(
				$this->getUrl() . '/v1/send/' . $identifier,
				[
					'json' => [ 'message' => $message ],
				]
			);
			$body = $response->getBody();
			$json = json_decode($body, true);
			if ($response->getStatusCode() !== 201 || is_null($json) || !is_array($json) || !isset($json['timestamp'])) {
				$status = $response->getStatusCode();
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
			$body = $response->getBody();
			$json = json_decode($body, true);

			if ($response->getStatusCode() !== 200 || is_null($json) || !is_array($json) || !isset($json['success']) || $json['success'] !== true) {
				$status = $response->getStatusCode();
				throw new MessageTransmissionException("error reported by Signal gateway, status=$status, body=$body}");
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
		return 0;
	}
}
