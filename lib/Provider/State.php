<?php

/**
 * SPDX-FileCopyrightText: 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider;

use JsonSerializable;
use OCA\TwoFactorGateway\Service\StateStorage;
use OCP\IUser;

/**
 * @psalm-type TwoFactorGatewayState = array{
 *     gatewayName: string,
 *     state: int,
 *     phoneNumber: ?string,
 * }
 *
 * @psalm-assert-if-true TwoFactorGatewayState $this
 */
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
			StateStorage::STATE_VERIFYING,
			$gatewayName,
			$identifier,
			$verificationCode
		);
	}

	public static function disabled(IUser $user, string $gatewayName): State {
		return new State(
			$user,
			StateStorage::STATE_DISABLED,
			$gatewayName
		);
	}

	public function verify(): State {
		return new State(
			$this->user,
			StateStorage::STATE_ENABLED,
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
