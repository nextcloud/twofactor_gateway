<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * Nextcloud - Two-factor Gateway
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\TwoFactorGateway\Command;

use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\Gateway\Signal\Gateway as SignalGateway;
use OCA\TwoFactorGateway\Service\Gateway\Signal\GatewayConfig as SignalConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Gateway as SMSGateway;
use OCA\TwoFactorGateway\Service\Gateway\SMS\GatewayConfig as SMSConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\ClickSendConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\ClockworkSMSConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\CustomSMS;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\CustomSMSConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\EcallSMSConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\PlaySMSConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\Sms77IoConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\OvhConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\WebSmsConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\PuzzelSMSConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\HuaweiE3531Config;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\SpryngSMSConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\ClickatellCentralConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\VoipbusterConfig;
use OCA\TwoFactorGateway\Service\Gateway\Telegram\Gateway as TelegramGateway;
use OCA\TwoFactorGateway\Service\Gateway\Telegram\GatewayConfig as TelegramConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Configure extends Command {

	/** @var SignalGateway */
	private $signalGateway;

	/** @var SMSGateway */
	private $smsGateway;

	/** @var TelegramGateway */
	private $telegramGateway;

	public function __construct(SignalGateway $signalGateway,
								SMSGateway $smsGateway,
								TelegramGateway $telegramGateway) {
		parent::__construct('twofactorauth:gateway:configure');
		$this->signalGateway = $signalGateway;
		$this->smsGateway = $smsGateway;
		$this->telegramGateway = $telegramGateway;

		$this->addArgument(
			'gateway',
			InputArgument::REQUIRED,
			'The name of the gateway, e.g. sms, signal, telegram, etc.'
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$gatewayName = $input->getArgument('gateway');

		/** @var IGateway $gateway */
		$gateway = null;
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
			default:
				$output->writeln("<error>Invalid gateway $gatewayName</error>");
				return;
		}
	}

	private function configureSignal(InputInterface $input, OutputInterface $output) {
		$helper = $this->getHelper('question');
		$urlQuestion = new Question('Please enter the URL of the Signal gateway (leave blank to use default): ', 'http://localhost:5000');
		$url = $helper->ask($input, $output, $urlQuestion);
		$output->writeln("Using $url.");

		/** @var SignalConfig $config */
		$config = $this->signalGateway->getConfig();

		$config->setUrl($url);
	}

	private function configureSms(InputInterface $input, OutputInterface $output) {
		$helper = $this->getHelper('question');

		$providerQuestion = new Question('Please choose a SMS provider (customsms, websms, playsms, clockworksms, puzzelsms, ecallsms, voipms, voipbuster, huawei_e3531, spryng, sms77io, ovh, clickatellcentral, clicksend): ', 'customsms');
		$provider = $helper->ask($input, $output, $providerQuestion);

		/** @var SMSConfig $config */
		$config = $this->smsGateway->getConfig();
		switch ($provider) {
			case 'customsms':
				$config->setProvider($provider);
				/** @var CustomSMSConfig $providerConfig */
				$providerConfig = $config->getProvider()->getConfig();

				ReTypeUrl:
				$urlQuestion = new Question('Please enter the web service URL: ');
				$url = $helper->ask($input, $output, $urlQuestion);

				if(!filter_var($url, FILTER_VALIDATE_URL))
				{
					$output->writeln('Invalid URL '.$url);
					goto ReTypeUrl;
				}

				ReTypeMethod:
				$methodQuestion = new Question('Please enter the web service method (GET or POST): ');
				$method = (string)$helper->ask($input, $output, $methodQuestion);

				if(strtolower($method) != 'get' && strtolower($method) != 'post')
				{
					$output->writeln('Invalid method '.$method);
					goto ReTypeMethod;
				}

				ReTypeIdentifier:
				$identifierQuestion = new Question('Please enter the identifier parameter for mobile number (Eg. mobile or number): ');
				$identifier = $helper->ask($input, $output, $identifierQuestion);

				if(empty($identifier))
				{
					$output->writeln('Mobile number parameter cannot be empty');
					goto ReTypeIdentifier;
				}

				ReTypeMessage:
				$messageQuestion = new Question('Please enter the message parameter (Eg. msg, sms, message): ');
				$message = $helper->ask($input, $output, $messageQuestion);

				if(empty($message))
				{
					$output->writeln('Message parameter cannot be empty');
					goto ReTypeMessage;
				}

				ReTypeHeaders:
				$headersQuestion = new Question('Please enter any http headers (Eg. x-api-key=123456789&x-api-token=ABCD12345 etc.): ');
				$headers = $helper->ask($input, $output, $headersQuestion);

				if(!empty($headers))
				{
					parse_str($headers, $headers_array);
					if(!is_array($headers_array))
					{
						$output->writeln('headers is invalid');
						goto ReTypeHeaders;
					}
				}
				else
				{
					$headers='';
				}

				ReTypeParameters:
				$parametersQuestion = new Question('Please enter any extra parameters (Eg. sender=nextcloud&username=nextcloud&password=1234 etc.): ');
				$parameters = $helper->ask($input, $output, $parametersQuestion);

				if(!empty($parameters))
				{
					parse_str($parameters, $parameters_array);
					if(!is_array($parameters_array))
					{
						$output->writeln('Extra parameters is invalid');
						goto ReTypeParameters;
					}
				}
				else
				{
					$parameters='';
				}

				$providerConfig->setUrl($url);
				$providerConfig->setMethod($method);
				$providerConfig->setIdentifier($identifier);
				$providerConfig->setMessage($message);
				$providerConfig->setHeaders($headers);
				$providerConfig->setParameters($parameters);

				break;
			case 'websms':
				$config->setProvider($provider);
				/** @var WebSmsConfig $providerConfig */
				$providerConfig = $config->getProvider()->getConfig();

				$usernameQuestion = new Question('Please enter your websms.de username: ');
				$username = $helper->ask($input, $output, $usernameQuestion);
				$passwordQuestion = new Question('Please enter your websms.de password: ');
				$password = $helper->ask($input, $output, $passwordQuestion);

				$providerConfig->setUser($username);
				$providerConfig->setPassword($password);

				break;
			case 'playsms':
				$config->setProvider($provider);
				/** @var PlaySMSConfig $providerConfig */
				$providerConfig = $config->getProvider()->getConfig();

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
				$config->setProvider($provider);
				/** @var ClockworkSMSConfig $providerConfig */
				$providerConfig = $config->getProvider()->getConfig();

				$apitokenQuestion = new Question('Please enter your clockworksms api token: ');
				$apitoken = $helper->ask($input, $output, $apitokenQuestion);

				$providerConfig->setApiToken($apitoken);

				break;
			case 'puzzelsms':
				$config->setProvider($provider);

				/** @var PuzzelSMSConfig $providerConfig */
				$providerConfig = $config->getProvider()->getConfig();

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
				$providerConfig->setServiceId($serviceId);
				break;
			case 'ecallsms':
				$config->setProvider($provider);
				/** @var EcallSMSConfig $providerConfig */
				$providerConfig = $config->getProvider()->getConfig();

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
				$config->setProvider($provider);

				/** @var VoipMsConfig $providerConfig */
				$providerConfig = $config->getProvider()->getConfig();

				$usernameQuestion = new Question('Please enter your VoIP.ms API username: ');
				$username = $helper->ask($input, $output, $usernameQuestion);

				$passwordQuestion = new Question('Please enter your VoIP.ms API password: ');
				$password = $helper->ask($input, $output, $passwordQuestion);

				$didQuestion = new Question('Please enter your VoIP.ms DID: ');
				$did = $helper->ask($input, $output, $didQuestion);

				$providerConfig->setUser($username);
				$providerConfig->setPassword($password);
				$providerConfig->setDid($did);
				break;

			case 'voipbuster':
				$config->setProvider($provider);
		
				/** @var VoipbusterConfig $providerConfig */
				$providerConfig = $config->getProvider()->getConfig();
		
				$usernameQuestion = new Question('Please enter your Voipbuster API username: ');
				$username = $helper->ask($input, $output, $usernameQuestion);
		
				$passwordQuestion = new Question('Please enter your Voipbuster API password: ');
				$password = $helper->ask($input, $output, $passwordQuestion);
		
				$didQuestion = new Question('Please enter your Voipbuster DID: ');
				$did = $helper->ask($input, $output, $didQuestion);
		
				$providerConfig->setUser($username);
				$providerConfig->setPassword($password);
				$providerConfig->setDid($did);
				break;

			case 'huawei_e3531':
				$config->setProvider($provider);
				/** @var HuaweiE3531Config $providerConfig */
				$providerConfig = $config->getProvider()->getConfig();

				$urlQuestion = new Question('Please enter the base URL of the Huawei E3531 stick: ', 'http://192.168.8.1/api');
				$url = $helper->ask($input, $output, $urlQuestion);

				$providerConfig->setUrl($url);
				break;

			case 'spryng':
				$config->setProvider($provider);
				/** @var SpryngSMSConfig $providerConfig */
				$providerConfig = $config->getProvider()->getConfig();

				$apitokenQuestion = new Question('Please enter your Spryng api token: ');
				$apitoken = $helper->ask($input, $output, $apitokenQuestion);

				$providerConfig->setApiToken($apitoken);

				break;

			case 'sms77io':
				$config->setProvider($provider);
				/** @var Sms77IoConfig $providerConfig */
				$providerConfig = $config->getProvider()->getConfig();

				$apiKeyQuestion = new Question('Please enter your sms77.io API key: ');
				$apiKey = $helper->ask($input, $output, $apiKeyQuestion);

				$providerConfig->setApiKey($apiKey);
				break;

			case 'ovh':
				$config->setProvider($provider);

				/** @var OvhConfig $providerConfig */
				$providerConfig = $config->getProvider()->getConfig();

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
				$config->setProvider($provider);
				/** @var ClickatellCentralConfig $providerConfig */
				$providerConfig = $config->getProvider()->getConfig();

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

			case 'clicksend':
				$config->setProvider($provider);
				/** @var ClickSendConfig $providerConfig */
				$providerConfig = $config->getProvider()->getConfig();

				$usernameQuestion = new Question('Please enter your clicksend.com username: ');
				$username = $helper->ask($input, $output, $usernameQuestion);
				$apiKeyQuestion = new Question('Please enter your clicksend.com API Key (or, if subuser, the password): ');
				$apiKey = $helper->ask($input, $output, $apiKeyQuestion);

				$providerConfig->setUser($username);
				$providerConfig->setApiKey($apiKey);

				break;

			default:
				$output->writeln("Invalid provider $provider");
				break;
		}
		return 0;
	}

	private function configureTelegram(InputInterface $input, OutputInterface $output) {
		$helper = $this->getHelper('question');
		$tokenQuestion = new Question('Please enter your Telegram bot token: ');
		$token = $helper->ask($input, $output, $tokenQuestion);
		$output->writeln("Using $token.");

		/** @var TelegramConfig $config */
		$config = $this->telegramGateway->getConfig();

		$config->setBotToken($token);
	}
}
