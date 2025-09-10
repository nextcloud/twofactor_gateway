<?php

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\TwoFactorGateway\Provider;

use JsonSerializable;
use OCP\IUser;

class State implements JsonSerializable {

	public function __construct(
		private IUser $user,
		private int $state,
		private string $gatewayName,
		private ?string $identifier = null,
		private ?string $verificationCode = null,
	) {
	}

	public static function verifying(IUser $user,
		string $gatewayName,
		string $identifier,
		string $verificationCode): State {
		return new State(
			$user,
			SmsProvider::STATE_VERIFYING,
			$gatewayName,
			$identifier,
			$verificationCode
		);
	}

	public static function disabled(IUser $user, string $gatewayName): State {
		return new State(
			$user,
			SmsProvider::STATE_DISABLED,
			$gatewayName
		);
	}

	public function verify(): State {
		return new State(
			$this->user,
			SmsProvider::STATE_ENABLED,
			$this->gatewayName,
			$this->identifier,
			$this->verificationCode
		);
	}

	public function getUser(): IUser {
		return $this->user;
	}

	public function getState(): int {
		return $this->state;
	}

	public function getGatewayName(): string {
		return $this->gatewayName;
	}

	public function getIdentifier(): ?string {
		return $this->identifier;
	}

	public function getVerificationCode(): ?string {
		return $this->verificationCode;
	}

	#[\Override]
	public function jsonSerialize(): mixed {
		return [
			'gatewayName' => $this->gatewayName,
			'state' => $this->state,
			'phoneNumber' => $this->identifier,
		];
	}
}
