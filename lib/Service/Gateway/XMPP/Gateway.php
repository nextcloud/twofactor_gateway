<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Rainer Dohmen <rdohmen@pensionmoselblick.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Service\Gateway\XMPP;

use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\Gateway\IGatewayConfig;
use OCP\IAppConfig;
use OCP\IUser;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Gateway implements IGateway {

	public function __construct(
		private GatewayConfig $gatewayConfig,
		public IAppConfig $config,
		private LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function send(IUser $user, string $identifier, string $message, array $extra = []): void {
		$this->logger->debug("sending xmpp message to $identifier, message: $message");

		$sender = $this->gatewayConfig->getSender();
		$password = $this->gatewayConfig->getPassword();
		$server = $this->gatewayConfig->getServer();
		$method = $this->gatewayConfig->getMethod();
		$user = $this->gatewayConfig->getUsername();
		$url = $server . $identifier;

		if ($method === '1') {
			$from = $user;
		}
		if ($method === '2') {
			$from = $sender;
		}
		$this->logger->debug("URL: $url, sender: $sender, method: $method");

		try {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
			curl_setopt($ch, CURLOPT_USERPWD, $from . ':' . $password);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/plain']);
			$result = curl_exec($ch);
			curl_close($ch);
			$this->logger->debug("XMPP message to $identifier sent");
		} catch (\Exception) {
			throw new MessageTransmissionException();
		}
	}

	/**
	 * @return GatewayConfig
	 */
	#[\Override]
	public function getConfig(): IGatewayConfig {
		return $this->gatewayConfig;
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$helper = new QuestionHelper();
		$fields = $this->gatewayConfig::SCHEMA['fields'];
		$fields = array_combine(array_column($fields, 'field'), $fields);
		$sender = '';
		while (empty($sender) or substr_count($sender, '@') !== 1) {
			$senderQuestion = new Question($fields['sender']['prompt']);
			$sender = $helper->ask($input, $output, $senderQuestion);
			if (empty($sender)) {
				$output->writeln('XMPP-JID must not be empty!');
			} elseif (substr_count($sender, '@') !== 1) {
				$output->writeln('XMPP-JID not valid!');
			} else {
				$username = explode('@', $sender)[0];
			}
		}
		$output->writeln("Using $sender as XMPP-JID.\nUsing $username as username.");
		$password = '';
		while (empty($password)) {
			$passwordQuestion = new Question($fields['password']['prompt']);
			$password = $helper->ask($input, $output, $passwordQuestion);
			if (empty($password)) {
				$output->writeln('Password must not be empty!');
			}
		}
		$output->writeln('Password accepted.');
		$server = '';
		while (empty($server)) {
			$serverQuestion = new Question($fields['server']['prompt']);
			$server = $helper->ask($input, $output, $serverQuestion);
			if (empty($server)) {
				$output->writeln('API path must not be empty!');
			}
		}
		$output->writeln("Using $server as full URL to access REST/HTTP API.");
		$method = 0;
		while (intval($method) < 1 or intval($method) > 2) {
			echo $fields['method']['promt'] . PHP_EOL;
			echo "(1) prosody with mod_rest\n";
			echo "(2) prosody with mod_post_msg\n";
			$methodQuestion = new Question('Your choice: ');
			$method = $helper->ask($input, $output, $methodQuestion);
		}
		if ($method === '1') {
			$output->writeln('Using prosody with mod_rest as XMPP sending option.');
		} elseif ($method === '2') {
			$output->writeln('Using prosody with mod_post_msg as XMPP sending option.');
		}
		$output->writeln('XMPP Admin Configuration finished.');

		$this->gatewayConfig->setSender($sender);
		$this->gatewayConfig->setPassword($password);
		$this->gatewayConfig->setServer($server);
		$this->gatewayConfig->setUsername($username);
		$this->gatewayConfig->setMethod($method);
		return 0;
	}
}
