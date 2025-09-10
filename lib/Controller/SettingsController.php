<?php

/**
 * SPDX-FileCopyrightText: 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Controller;

use OCA\TwoFactorGateway\Exception\VerificationException;
use OCA\TwoFactorGateway\Service\Gateway\Factory as GatewayFactory;
use OCA\TwoFactorGateway\Service\SetupService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class SettingsController extends Controller {

	/** @var IUserSession */
	private $userSession;

	/** @var SetupService */
	private $setup;

	/** @var GatewayFactory */
	private $gatewayFactory;

	public function __construct(IRequest $request,
		IUserSession $userSession,
		SetupService $setup,
		GatewayFactory $gatewayFactory) {
		parent::__construct('twofactor_gateway', $request);

		$this->userSession = $userSession;
		$this->setup = $setup;
		$this->gatewayFactory = $gatewayFactory;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getVerificationState(string $gateway): JSONResponse {
		$user = $this->userSession->getUser();

		if (is_null($user)) {
			return new JSONResponse(null, Http::STATUS_BAD_REQUEST);
		}

		$gatewayConfig = $this->gatewayFactory->getGateway($gateway)->getConfig();
		if (!$gatewayConfig->isComplete()) {
			return new JSONResponse(null, Http::STATUS_SERVICE_UNAVAILABLE);
		}

		return new JSONResponse($this->setup->getState($user, $gateway));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $identification
	 *
	 * @return JSONResponse
	 */
	public function startVerification(string $gateway, string $identifier): JSONResponse {
		$user = $this->userSession->getUser();

		if (is_null($user)) {
			return new JSONResponse(null, Http::STATUS_BAD_REQUEST);
		}

		$state = $this->setup->startSetup($user, $gateway, $identifier);

		return new JSONResponse([
			'phoneNumber' => $state->getIdentifier(),
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function finishVerification(string $gateway, string $verificationCode): JSONResponse {
		$user = $this->userSession->getUser();

		if (is_null($user)) {
			return new JSONResponse(null, Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->setup->finishSetup($user, $gateway, $verificationCode);
		} catch (VerificationException $ex) {
			return new JSONResponse(null, Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse([]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function revokeVerification(string $gateway): JSONResponse {
		$user = $this->userSession->getUser();

		if (is_null($user)) {
			return new JSONResponse(null, Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse($this->setup->disable($user, $gateway));
	}
}
