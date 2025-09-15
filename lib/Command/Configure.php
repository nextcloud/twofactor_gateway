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
use Symfony\Component\Console\Question\ChoiceQuestion;
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
		$config = $this->signalGateway->getConfig();
		$helper = new QuestionHelper();
		$urlQuestion = new Question($config::SCHEMA['fields'][0]['prompt'], $config::SCHEMA['fields'][0]['default']);
		$url = $helper->ask($input, $output, $urlQuestion);
		$output->writeln("Using $url.");

		$this->signalGateway->getConfig()->setUrl($url);
	}

	private function configureSms(InputInterface $input, OutputInterface $output): int {
		$schemas = $this->smsGateway->getProvidersSchemas();
		$names = array_column($schemas, 'name');

		$helper = new QuestionHelper();
		$choiceQuestion = new ChoiceQuestion('Please choose a SMS provider:', $names);
		$name = $helper->ask($input, $output, $choiceQuestion);
		$selectedIndex = array_search($name, $names);
		$schema = $schemas[$selectedIndex]['id'];

		$config = $this->smsGateway->getConfig()->getProvider($schema['id'])->config;

		foreach ($schema['fields'] as $field) {
			$id = $field['field'];
			$prompt = $field['prompt'];
			$defaultVal = $field['default'] ?? null;
			$optional = (bool)($field['optional'] ?? false);

			$answer = (string)$helper->ask($input, $output, new Question($prompt, $defaultVal));

			if ($optional && $answer === '') {
				$method = 'delete' . $this->toCamel($id);
				$config->{$method}();
				continue;
			}

			$method = 'set' . $this->toCamel($id);
			$config->{$method}($answer);
		}
		return 0;
	}

	private function toCamel(string $field): string {
		return str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
	}

	private function configureTelegram(InputInterface $input, OutputInterface $output): void {
		$helper = new QuestionHelper();
		$tokenQuestion = new Question($this->telegramGateway->getConfig()::SCHEMA['fields'][0]['prompt']);
		$token = $helper->ask($input, $output, $tokenQuestion);
		$output->writeln("Using $token.");

		$this->telegramGateway->getConfig()->setBotToken($token);
	}

	private function configureXMPP(InputInterface $input, OutputInterface $output): void {
		$helper = new QuestionHelper();
		$fields = $this->xmppGateway->getConfig()::SCHEMA['fields'];
		$fields = array_combine(array_column($fields, 'field'), $fields);
		$sender = '';
		while (empty($sender) or substr_count($sender, '@') !== 1):
			$senderQuestion = new Question($fields['sender']['prompt']);
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
			$passwordQuestion = new Question($fields['password']['prompt']);
			$password = $helper->ask($input, $output, $passwordQuestion);
			if (empty($password)) {
				$output->writeln('Password must not be empty!');
			}
		endwhile;
		$output->writeln('Password accepted.');
		$server = '';
		while (empty($server)):
			$serverQuestion = new Question($fields['server']['prompt']);
			$server = $helper->ask($input, $output, $serverQuestion);
			if (empty($server)) {
				$output->writeln('API path must not be empty!');
			}
		endwhile;
		$output->writeln("Using $server as full URL to access REST/HTTP API.");
		$method = 0;
		while (intval($method) < 1 or intval($method) > 2):
			echo $fields['method']['promt'] . PHP_EOL;
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
