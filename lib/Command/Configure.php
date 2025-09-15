<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Service\Gateway\Signal\Gateway as SignalGateway;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Gateway as SMSGateway;
use OCA\TwoFactorGateway\Service\Gateway\Telegram\Gateway as TelegramGateway;
use OCA\TwoFactorGateway\Service\Gateway\XMPP\Gateway as XMPPGateway;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Configure extends Command {

	private const SMS_SCHEMA = [
		'websms' => [
			['alias' => 'user',     'prompt' => 'Please enter your websms.de username: '],
			['alias' => 'password', 'prompt' => 'Please enter your websms.de password: '],
		],
		'sipgate' => [
			['alias' => 'token_id',        'prompt' => 'Please enter your sipgate token-id: '],
			['alias' => 'access_token',    'prompt' => 'Please enter your sipgate access token: '],
			['alias' => 'web_sms_extension','prompt' => 'Please enter your sipgate web-sms extension: '],
		],
		'playsms' => [
			['alias' => 'url',      'prompt' => 'Please enter your PlaySMS URL: '],
			['alias' => 'user',     'prompt' => 'Please enter your PlaySMS username: '],
			['alias' => 'password', 'prompt' => 'Please enter your PlaySMS password: '],
		],
		'clockworksms' => [
			['alias' => 'apitoken', 'prompt' => 'Please enter your clockworksms api token: '],
		],
		'puzzelsms' => [
			['alias' => 'url',       'prompt' => 'Please enter your PuzzelSMS URL: '],
			['alias' => 'user',      'prompt' => 'Please enter your PuzzelSMS username: '],
			['alias' => 'password',  'prompt' => 'Please enter your PuzzelSMS password: '],
			['alias' => 'serviceid', 'prompt' => 'Please enter your PuzzelSMS service ID: '],
		],
		'ecallsms' => [
			['alias' => 'user',      'prompt' => 'Please enter your eCall.ch username: '],
			['alias' => 'password',  'prompt' => 'Please enter your eCall.ch password: '],
			['alias' => 'sender_id', 'prompt' => 'Please enter your eCall.ch sender ID: '],
		],
		'voipms' => [
			['alias' => 'api_user',     'prompt' => 'Please enter your VoIP.ms API username: '],
			['alias' => 'api_password', 'prompt' => 'Please enter your VoIP.ms API password: '],
			['alias' => 'did',          'prompt' => 'Please enter your VoIP.ms DID: '],
		],
		'voipbuster' => [
			['alias' => 'api_user',     'prompt' => 'Please enter your Voipbuster API username: '],
			['alias' => 'api_password', 'prompt' => 'Please enter your Voipbuster API password: '],
			['alias' => 'did',          'prompt' => 'Please enter your Voipbuster DID: '],
		],
		'huawei_e3531' => [
			['alias' => 'api', 'prompt' => 'Please enter the base URL of the Huawei E3531 stick: ', 'default' => 'http://192.168.8.1/api'],
		],
		'spryng' => [
			['alias' => 'apitoken', 'prompt' => 'Please enter your Spryng api token: '],
		],
		'sms77io' => [
			['alias' => 'api_key', 'prompt' => 'Please enter your sms77.io API key: '],
		],
		'ovh' => [
			['alias' => 'endpoint',        'prompt' => 'Please enter the endpoint (ovh-eu, ovh-us, ovh-ca, soyoustart-eu, soyoustart-ca, kimsufi-eu, kimsufi-ca, runabove-ca): '],
			['alias' => 'application_key', 'prompt' => 'Please enter your application key: '],
			['alias' => 'application_secret','prompt' => 'Please enter your application secret: '],
			['alias' => 'consumer_key',    'prompt' => 'Please enter your consumer key: '],
			['alias' => 'account',         'prompt' => 'Please enter your account (sms-*****): '],
			['alias' => 'sender',          'prompt' => 'Please enter your sender: '],
		],
		'clickatellcentral' => [
			['alias' => 'api',      'prompt' => 'Please enter your central.clickatell.com API-ID: '],
			['alias' => 'user',     'prompt' => 'Please enter your central.clickatell.com username: '],
			['alias' => 'password', 'prompt' => 'Please enter your central.clickatell.com password: '],
		],
		'clickatellportal' => [
			['alias' => 'api_key', 'prompt' => 'Please enter your portal.clickatell.com API-Key: '],
			['alias' => 'from',    'prompt' => 'Please enter your sender number for two-way messaging (empty = one-way): ', 'optional' => true],
		],
		'clicksend' => [
			['alias' => 'user',    'prompt' => 'Please enter your clicksend.com username: '],
			['alias' => 'api_key', 'prompt' => 'Please enter your clicksend.com API Key (or subuser password): '],
		],
		'serwersms' => [
			['alias' => 'login',    'prompt' => 'Please enter your SerwerSMS.pl API login: '],
			['alias' => 'password', 'prompt' => 'Please enter your SerwerSMS.pl API password: '],
			['alias' => 'sender',   'prompt' => 'Please enter your SerwerSMS.pl sender name: '],
		],
		'smsglobal' => [
			['alias' => 'url',      'prompt' => 'Please enter your SMSGlobal http-api:', 'default' => 'https://api.smsglobal.com/http-api.php'],
			['alias' => 'user',     'prompt' => 'Please enter your SMSGlobal username (for http-api): '],
			['alias' => 'password', 'prompt' => 'Please enter your SMSGlobal password (for http-api): '],
		],
		'smsapi.com' => [
			['alias' => 'token', 'prompt' => 'Please enter your SMSApi.com API token: '],
			['alias' => 'sender','prompt' => 'Please enter your SMSApi.com sender name: '],
		],
	];


	public function __construct(
		private SignalGateway $signalGateway,
		private SMSGateway $smsGateway,
		private TelegramGateway $telegramGateway,
		private XMPPGateway $xmppGateway,
	) {
		parent::__construct('twofactorauth:gateway:configure');

		$this->addArgument(
			'gateway',
			InputArgument::REQUIRED,
			'The name of the gateway, e.g. sms, signal, telegram, xmpp, etc.'
		);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output) {
		$gatewayName = strtolower((string)$input->getArgument('gateway'));

		switch ($gatewayName) {
			case 'signal':
				$this->configureSignal($input, $output);
				return 0;
			case 'sms':
				$this->configureSms($input, $output);
				return 0;
			case 'telegram':
				$this->configureTelegram($input, $output);
				return 0;
			case 'xmpp':
				$this->configureXMPP($input, $output);
				return 0;
			default:
				$output->writeln("<error>Invalid gateway $gatewayName</error>");
				return 1;
		}
	}

	private function configureSignal(InputInterface $input, OutputInterface $output): void {
		$helper = new QuestionHelper();
		$urlQuestion = new Question('Please enter the URL of the Signal gateway (leave blank to use default): ', 'http://localhost:5000');
		$url = $helper->ask($input, $output, $urlQuestion);
		$output->writeln("Using $url.");

		$this->signalGateway->getConfig()->setUrl($url);
	}

	private function configureSms(InputInterface $input, OutputInterface $output): int {
		$providers = array_keys(self::SMS_SCHEMA);
		$providerQuestion = new Question('Please choose a SMS provider (' . implode(', ', $providers) . '): ', 'websms');

		$helper = new QuestionHelper();
		$provider = $helper->ask($input, $output, $providerQuestion);

		if (!isset(self::SMS_SCHEMA[$provider])) {
			$output->writeln("<error>Invalid provider $provider</error>");
			return Command::INVALID;
		}

		$config = $this->smsGateway->getConfig()->getProvider($provider)->config;

		foreach (self::SMS_SCHEMA[$provider] as $q) {
			$alias = $q['alias'];
			$prompt = $q['prompt'];
			$defaultVal = $q['default'] ?? null;
			$optional = (bool)($q['optional'] ?? false);

			$answer = (string)$helper->ask($input, $output, new Question($prompt, $defaultVal));

			if ($optional && $answer === '') {
				$delete = 'delete' . $this->toCamel($alias);
				$config->{$delete}();
				continue;
			}

			$method = 'set' . $this->toCamel($alias);
			$config->{$method}($answer);
		}
		return 0;
	}

	private function toCamel(string $alias): string {
		return str_replace(' ', '', ucwords(str_replace('_', ' ', $alias)));
	}

	private function configureTelegram(InputInterface $input, OutputInterface $output): void {
		$helper = new QuestionHelper();
		$tokenQuestion = new Question('Please enter your Telegram bot token: ');
		$token = $helper->ask($input, $output, $tokenQuestion);
		$output->writeln("Using $token.");

		$this->telegramGateway->getConfig()->setBotToken($token);
	}

	private function configureXMPP(InputInterface $input, OutputInterface $output): void {
		$helper = new QuestionHelper();
		$sender = '';
		while (empty($sender) or substr_count($sender, '@') !== 1):
			$senderQuestion = new Question('Please enter your sender XMPP-JID: ');
			$sender = $helper->ask($input, $output, $senderQuestion);
			if (empty($sender)) {
				$output->writeln('XMPP-JID must not be empty!');
			} elseif (substr_count($sender, '@') !== 1) {
				$output->writeln('XMPP-JID not valid!');
			} else {
				$username = explode('@', $sender)[0];
			}
		endwhile;
		$output->writeln("Using $sender as XMPP-JID.\nUsing $username as username.");
		$password = '';
		while (empty($password)):
			$passwordQuestion = new Question('Please enter your sender XMPP password: ');
			$password = $helper->ask($input, $output, $passwordQuestion);
			if (empty($password)) {
				$output->writeln('Password must not be empty!');
			}
		endwhile;
		$output->writeln('Password accepted.');
		$server = '';
		while (empty($server)):
			$serverQuestion = new Question('Please enter full path to access REST/HTTP API: ');
			$server = $helper->ask($input, $output, $serverQuestion);
			if (empty($server)) {
				$output->writeln('API path must not be empty!');
			}
		endwhile;
		$output->writeln("Using $server as full URL to access REST/HTTP API.");
		$method = 0;
		while (intval($method) < 1 or intval($method) > 2):
			echo "Please enter 1 or 2 for XMPP sending option:\n";
			echo "(1) prosody with mod_rest\n";
			echo "(2) prosody with mod_post_msg\n";
			$methodQuestion = new Question('Your choice: ');
			$method = $helper->ask($input, $output, $methodQuestion);
		endwhile;
		if ($method === '1') {
			$output->writeln('Using prosody with mod_rest as XMPP sending option.');
		} elseif ($method === '2') {
			$output->writeln('Using prosody with mod_post_msg as XMPP sending option.');
		}
		$output->writeln('XMPP Admin Configuration finished.');

		$this->xmppGateway->getConfig()->setSender($sender);
		$this->xmppGateway->getConfig()->setPassword($password);
		$this->xmppGateway->getConfig()->setServer($server);
		$this->xmppGateway->getConfig()->setUsername($username);
		$this->xmppGateway->getConfig()->setMethod($method);
	}
}
