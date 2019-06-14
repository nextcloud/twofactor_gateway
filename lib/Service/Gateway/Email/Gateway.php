<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author André Fondse <andre@hetnetwerk.org>
 *
 * Nextcloud - Two-factor Gateway for Telegram
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

namespace OCA\TwoFactorGateway\Service\Gateway\Email;

use OCA\TwoFactorGateway\Exception\SmsTransmissionException;
use OCA\TwoFactorGateway\Service\Gateway\IGateway;
use OCA\TwoFactorGateway\Service\Gateway\IGatewayConfig;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUser;
use OCP\Mail\IMailer;
use OCP\Util;

class Gateway implements IGateway {

	/** @var IClient */
	private $client;

	/** @var GatewayConfig */
	private $gatewayConfig;

	/** @var IConfig */
	private $config;

	/** @var ILogger */
	private $logger;

	public function __construct(IClientService $clientService,
								GatewayConfig $gatewayConfig,
								IConfig $config,
								ILogger $logger) {
		$this->client = $clientService->newClient();
		$this->gatewayConfig = $gatewayConfig;
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * @param IUser $user
	 * @param string $identifier
	 * @param string $message
	 *
	 * @throws SmsTransmissionException
	 */
	public function send(IUser $user, string $identifier, string $message) {
		$this->logger->debug("sending email message to $identifier, message: $message");
        
        $mailer = \OC::$server->getMailer();
        $email = $mailer->createMessage();
        $email->setSubject("Nextcloud Account Verification");
        $email->setFrom([Util::getDefaultEmailAddress('no-reply') => "Nextcloud"]);
        $email->setTo([ $identifier => $user->getDisplayName() ]);
        $email->setPlainBody($message);
        $mailer->send($email);
	}

	/**
	 * Get the gateway-specific configuration
	 *
	 * @return IGatewayConfig
	 */
	public function getConfig(): IGatewayConfig {
		return $this->gatewayConfig;
	}

}
