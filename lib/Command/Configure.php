<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Service\Gateway\Signal\Gateway as SignalGateway;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Gateway as SMSGateway;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\ClickatellCentralConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\ClickatellPortalConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\ClickSendConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\ClockworkSMSConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\EcallSMSConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\HuaweiE3531Config;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\OvhConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\PlaySMSConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\PuzzelSMSConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\SerwerSMSConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\SipGateConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\Sms77IoConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\SMSApiConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\SMSGlobalConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\SpryngSMSConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\VoipbusterConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\VoipMsConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\WebSmsConfig;
use OCA\TwoFactorGateway\Service\Gateway\Telegram\Gateway as TelegramGateway;
use OCA\TwoFactorGateway\Service\Gateway\XMPP\Gateway as XMPPGateway;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Configure extends Command {

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

		$this->signalGateway->gatewayConfig->setUrl($url);
	}

	private function configureSms(InputInterface $input, OutputInterface $output): int {
		$helper = new QuestionHelper();

		$providerQuestion = new Question('Please choose a SMS provider (sipgate, websms, playsms, clockworksms, puzzelsms, ecallsms, voipms, voipbuster, huawei_e3531, spryng, sms77io, ovh, clickatellcentral, clickatellportal, clicksend, serwersms, smsglobal, smsapi.com): ', 'websms');
		$provider = $helper->ask($input, $output, $providerQuestion);

		switch ($provider) {
			case 'websms':
				/** @var WebSmsConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$usernameQuestion = new Question('Please enter your websms.de username: ');
				$username = $helper->ask($input, $output, $usernameQuestion);
				$passwordQuestion = new Question('Please enter your websms.de password: ');
				$password = $helper->ask($input, $output, $passwordQuestion);

				$providerConfig->setUser($username);
				$providerConfig->setPassword($password);
				break;

			case 'sipgate':
				/** @var SipGateConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$tokenIdQuestion = new Question('Please enter your sipgate token-id: ');
				$tokenId = $helper->ask($input, $output, $tokenIdQuestion);
				$accessTokenQuestion = new Question('Please enter your sipgate access token: ');
				$accessToken = $helper->ask($input, $output, $accessTokenQuestion);
				$webSmsExtensionQuestion = new Question('Please enter your sipgate web-sms extension: ');
				$webSmsExtension = $helper->ask($input, $output, $webSmsExtensionQuestion);

				$providerConfig->setTokenId($tokenId);
				$providerConfig->setAccessToken($accessToken);
				$providerConfig->setWebSmsExtension($webSmsExtension);
				break;

			case 'playsms':
				/** @var PlaySMSConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$urlQuestion = new Question('Please enter your PlaySMS URL: ');
				$url = $helper->ask($input, $output, $urlQuestion);
				$usernameQuestion = new Question('Please enter your PlaySMS username: ');
				$username = $helper->ask($input, $output, $usernameQuestion);
				$passwordQuestion = new Question('Please enter your PlaySMS password: ');
				$password = $helper->ask($input, $output, $passwordQuestion);

				$providerConfig->setUrl($url);
				$providerConfig->setUser($username);
				$providerConfig->setPassword($password);
				break;

			case 'clockworksms':
				/** @var ClockworkSMSConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$apitokenQuestion = new Question('Please enter your clockworksms api token: ');
				$apitoken = $helper->ask($input, $output, $apitokenQuestion);

				$providerConfig->setApitoken($apitoken);
				break;

			case 'puzzelsms':

				/** @var PuzzelSMSConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$urlQuestion = new Question('Please enter your PuzzelSMS URL: ');
				$url = $helper->ask($input, $output, $urlQuestion);

				$usernameQuestion = new Question('Please enter your PuzzelSMS username: ');
				$username = $helper->ask($input, $output, $usernameQuestion);

				$passwordQuestion = new Question('Please enter your PuzzelSMS password: ');
				$password = $helper->ask($input, $output, $passwordQuestion);

				$serviceQuestion = new Question('Please enter your PuzzelSMS service ID: ');
				$serviceId = $helper->ask($input, $output, $serviceQuestion);

				$providerConfig->setUrl($url);
				$providerConfig->setUser($username);
				$providerConfig->setPassword($password);
				$providerConfig->setServiceid($serviceId);
				break;

			case 'ecallsms':
				/** @var EcallSMSConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$usernameQuestion = new Question('Please enter your eCall.ch username: ');
				$username = $helper->ask($input, $output, $usernameQuestion);
				$passwordQuestion = new Question('Please enter your eCall.ch password: ');
				$password = $helper->ask($input, $output, $passwordQuestion);
				$senderIdQuestion = new Question('Please enter your eCall.ch sender ID: ');
				$senderId = $helper->ask($input, $output, $senderIdQuestion);

				$providerConfig->setUser($username);
				$providerConfig->setPassword($password);
				$providerConfig->setSenderId($senderId);
				break;

			case 'voipms':

				/** @var VoipMsConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$usernameQuestion = new Question('Please enter your VoIP.ms API username: ');
				$username = $helper->ask($input, $output, $usernameQuestion);

				$passwordQuestion = new Question('Please enter your VoIP.ms API password: ');
				$password = $helper->ask($input, $output, $passwordQuestion);

				$didQuestion = new Question('Please enter your VoIP.ms DID: ');
				$did = $helper->ask($input, $output, $didQuestion);

				$providerConfig->setApiUser($username);
				$providerConfig->setApiPassword($password);
				$providerConfig->setDid($did);
				break;

			case 'voipbuster':

				/** @var VoipbusterConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$usernameQuestion = new Question('Please enter your Voipbuster API username: ');
				$username = $helper->ask($input, $output, $usernameQuestion);

				$passwordQuestion = new Question('Please enter your Voipbuster API password: ');
				$password = $helper->ask($input, $output, $passwordQuestion);

				$didQuestion = new Question('Please enter your Voipbuster DID: ');
				$did = $helper->ask($input, $output, $didQuestion);

				$providerConfig->setApiUser($username);
				$providerConfig->setApiPassword($password);
				$providerConfig->setDid($did);
				break;

			case 'huawei_e3531':
				/** @var HuaweiE3531Config $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$urlQuestion = new Question('Please enter the base URL of the Huawei E3531 stick: ', 'http://192.168.8.1/api');
				$url = $helper->ask($input, $output, $urlQuestion);

				$providerConfig->setApi($url);
				break;

			case 'spryng':
				/** @var SpryngSMSConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$apitokenQuestion = new Question('Please enter your Spryng api token: ');
				$apitoken = $helper->ask($input, $output, $apitokenQuestion);

				$providerConfig->setApitoken($apitoken);
				break;

			case 'sms77io':
				/** @var Sms77IoConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$apiKeyQuestion = new Question('Please enter your sms77.io API key: ');
				$apiKey = $helper->ask($input, $output, $apiKeyQuestion);

				$providerConfig->setApiKey($apiKey);
				break;

			case 'ovh':

				/** @var OvhConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$endpointQ = new Question('Please enter the endpoint to use (ovh-eu, ovh-us, ovh-ca, soyoustart-eu, soyoustart-ca, kimsufi-eu, kimsufi-ca, runabove-ca): ');
				$endpoint = $helper->ask($input, $output, $endpointQ);

				$appKeyQ = new Question('Please enter your application key: ');
				$appKey = $helper->ask($input, $output, $appKeyQ);

				$appSecretQ = new Question('Please enter your application secret: ');
				$appSecret = $helper->ask($input, $output, $appSecretQ);

				$consumerKeyQ = new Question('Please enter your consumer key: ');
				$consumerKey = $helper->ask($input, $output, $consumerKeyQ);

				$accountQ = new Question('Please enter your account (sms-*****): ');
				$account = $helper->ask($input, $output, $accountQ);

				$senderQ = new Question('Please enter your sender: ');
				$sender = $helper->ask($input, $output, $senderQ);

				$providerConfig->setApplicationKey($appKey);
				$providerConfig->setApplicationSecret($appSecret);
				$providerConfig->setConsumerKey($consumerKey);
				$providerConfig->setEndpoint($endpoint);
				$providerConfig->setAccount($account);
				$providerConfig->setSender($sender);
				break;

			case 'clickatellcentral':
				/** @var ClickatellCentralConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$apiQuestion = new Question('Please enter your central.clickatell.com API-ID: ');
				$api = $helper->ask($input, $output, $apiQuestion);
				$usernameQuestion = new Question('Please enter your central.clickatell.com username: ');
				$username = $helper->ask($input, $output, $usernameQuestion);
				$passwordQuestion = new Question('Please enter your central.clickatell.com password: ');
				$password = $helper->ask($input, $output, $passwordQuestion);

				$providerConfig->setApi($api);
				$providerConfig->setUser($username);
				$providerConfig->setPassword($password);
				break;

			case 'clickatellportal':
				/** @var ClickatellPortalConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$apiQuestion = new Question('Please enter your portal.clickatell.com API-Key: ');
				$apiKey = $helper->ask($input, $output, $apiQuestion);
				$fromQuestion = new Question('Please enter your sender number for two-way messaging. Leave it empty for one-way messaging: ');
				$fromNumber = $helper->ask($input, $output, $fromQuestion);

				$providerConfig->setApiKey($apiKey);

				if (empty($fromNumber)) {
					$providerConfig->deleteFrom();
				} else {
					$providerConfig->setFrom($fromNumber);
				}
				break;

			case 'clicksend':
				/** @var ClickSendConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$usernameQuestion = new Question('Please enter your clicksend.com username: ');
				$username = $helper->ask($input, $output, $usernameQuestion);
				$apiKeyQuestion = new Question('Please enter your clicksend.com API Key (or, if subuser, the password): ');
				$apiKey = $helper->ask($input, $output, $apiKeyQuestion);

				$providerConfig->setUser($username);
				$providerConfig->setApiKey($apiKey);
				break;

			case 'smsglobal':
				/** @var SMSGlobalConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$urlproposal = 'https://api.smsglobal.com/http-api.php';
				$urlQuestion = new Question('Please enter your SMSGlobal http-api:', $urlproposal);
				$url = $helper->ask($input, $output, $urlQuestion);
				$usernameQuestion = new Question('Please enter your SMSGlobal username (for http-api):');
				$username = $helper->ask($input, $output, $usernameQuestion);
				$passwordQuestion = new Question('Please enter your SMSGlobal password: (for http-api):');
				$password = $helper->ask($input, $output, $passwordQuestion);

				$providerConfig->setUrl($url);
				$providerConfig->setUser($username);
				$providerConfig->setPassword($password);
				break;

			case 'serwersms':
				/** @var SerwerSMSConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$loginQuestion = new Question('Please enter your SerwerSMS.pl API login: ');
				$login = $helper->ask($input, $output, $loginQuestion);
				$passwordQuestion = new Question('Please enter your SerwerSMS.pl API password: ');
				$password = $helper->ask($input, $output, $passwordQuestion);
				$senderQuestion = new Question('Please enter your SerwerSMS.pl sender name: ');
				$sender = $helper->ask($input, $output, $senderQuestion);

				$providerConfig->setLogin($login);
				$providerConfig->setPassword($password);
				$providerConfig->setSender($sender);
				break;

			case 'smsapi.com':
				/** @var SMSApiConfig $providerConfig */
				$providerConfig = $this->smsGateway->config->getProvider($provider)->config;

				$tokenQuestion = new Question('Please enter your SMSApi.com API token: ');
				$token = $helper->ask($input, $output, $tokenQuestion);
				$senderQuestion = new Question('Please enter your SMSApi.com sender name: ');
				$sender = $helper->ask($input, $output, $senderQuestion);

				$providerConfig->setToken($token);
				$providerConfig->setSender($sender);
				break;

			default:
				$output->writeln("Invalid provider $provider");
				break;
		}
		return 0;
	}

	private function configureTelegram(InputInterface $input, OutputInterface $output): void {
		$helper = new QuestionHelper();
		$tokenQuestion = new Question('Please enter your Telegram bot token: ');
		$token = $helper->ask($input, $output, $tokenQuestion);
		$output->writeln("Using $token.");

		$this->telegramGateway->gatewayConfig->setBotToken($token);
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

		$this->xmppGateway->gatewayConfig->setSender($sender);
		$this->xmppGateway->gatewayConfig->setPassword($password);
		$this->xmppGateway->gatewayConfig->setServer($server);
		$this->xmppGateway->gatewayConfig->setUsername($username);
		$this->xmppGateway->gatewayConfig->setMethod($method);
	}
}
