<?php

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 201 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\TwoFactorGateway\Controller;

use OCA\TwoFactorGateway\Exception\VerificationException;
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

	public function __construct(IRequest $request, IUserSession $userSession,
								SetupService $setup) {
		parent::__construct('twofactor_gateway', $request);

		$this->userSession = $userSession;
		$this->setup = $setup;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getVerificationState(string $gateway): JSONResponse {
		$user = $this->userSession->getUser();

		if (is_null($user)) {
			return new JSONResponse(null, Http::STATUS_BAD_REQUEST);
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
