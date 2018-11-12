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
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\PlaySMSConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\WebSmsConfig;
use OCA\TwoFactorGateway\Service\Gateway\SMS\Provider\PuzzelSMSConfig;
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
			'The identifier (e.g. phone number) of the recipient'
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$gatewayName = $input->getArgument('gateway');

		/** @var IGateway $gateway */
		$gateway = null;
		switch ($gatewayName) {
			case 'signal':
				$this->configureSignal($input, $output);
				break;
			case 'sms':
				$this->configureSms($input, $output);
				break;
			case 'telegram':
				$this->configureTelegram($input, $output);
				break;
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
		$providerQuestion = new Question('Please choose a SMS provider (websms, playsms, clockworksms, puzzelsms): ', 'websms');
		$provider = $helper->ask($input, $output, $providerQuestion);

		/** @var SMSConfig $config */
		$config = $this->smsGateway->getConfig();
		switch ($provider) {
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
				/** @var ClockworkSmsConfig $providerConfig */
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

			default:
				$output->writeln("Invalid provider $provider");
				break;
		}

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