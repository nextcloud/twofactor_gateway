#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Telegram\Provider\Drivers\ClientCli;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** @psalm-suppress UndefinedClass */
#[AsCommand(name: 'telegram:get-account-info', description: 'Get the logged-in Telegram account name and avatar')]
class GetAccountInfo extends Command {
	#[\Override]
	protected function configure(): void {
		$this->addOption(
			'session-directory',
			's',
			InputOption::VALUE_REQUIRED,
			'Directory to store the session files',
		);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$sessionDirectory = $input->getOption('session-directory');

		$appInfo = new AppInfo();
		$appInfo->setDeviceModel('Nextcloud-TwoFactor-Gateway');
		if ($apiId = getenv('TELEGRAM_API_ID')) {
			$appInfo->setApiId((int)$apiId);
		}
		if ($apiHash = getenv('TELEGRAM_API_HASH')) {
			$appInfo->setApiHash($apiHash);
		}

		try {
			$settings = (new Settings())
				->setAppInfo($appInfo)
				->setLogger((new Logger())->setExtra($sessionDirectory . '/MadelineProto.log'));

			$api = new API($sessionDirectory, $settings);
			if ($api->getAuthorization() !== API::LOGGED_IN) {
				$output->writeln('<error>Error: Telegram Client session is not logged in. Complete the Telegram login flow first.</error>');
				return Command::FAILURE;
			}

			$api->start();
			$self = $api->fullGetSelf();
			if ($self === false) {
				$output->writeln('<error>Error: Unable to load Telegram account information.</error>');
				return Command::FAILURE;
			}

			$accountInfo = ['account_name' => $this->extractAccountName($self)];
			$avatarDataUri = $this->fetchAvatarDataUri($api, $self);
			if ($avatarDataUri !== '') {
				$accountInfo['account_avatar_url'] = $avatarDataUri;
			}

			$json = json_encode($accountInfo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			if (!is_string($json)) {
				$output->writeln('<error>Error: Unable to serialize Telegram account information.</error>');
				return Command::FAILURE;
			}

			$output->writeln($json);
			return Command::SUCCESS;
		} catch (\Throwable $e) {
			$output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}
	}

	/** @param array<string, mixed> $self */
	private function extractAccountName(array $self): string {
		$firstName = trim((string)($self['first_name'] ?? ''));
		$lastName = trim((string)($self['last_name'] ?? ''));
		$fullName = trim($firstName . ' ' . $lastName);
		if ($fullName !== '') {
			return $fullName;
		}

		$username = trim((string)($self['username'] ?? ''));
		if ($username !== '') {
			return '@' . ltrim($username, '@');
		}

		return (string)($self['id'] ?? 'Telegram');
	}

	/**
	 * @param object $api
	 * @param array<string, mixed> $self
	 */
	private function fetchAvatarDataUri(object $api, array $self): string {
		$photo = $this->fetchLatestUserPhoto($api, $self);
		if (!is_array($photo)) {
			return '';
		}

		$tempFile = tempnam(sys_get_temp_dir(), 'tg-avatar-');
		if ($tempFile === false) {
			return '';
		}

		try {
			$downloadedFile = $api->downloadToFile($photo, $tempFile);
			$avatarPath = is_string($downloadedFile) && $downloadedFile !== '' ? $downloadedFile : $tempFile;
			$avatarBytes = @file_get_contents($avatarPath);
			if (!is_string($avatarBytes) || $avatarBytes === '') {
				return '';
			}

			return sprintf('data:%s;base64,%s', $this->detectMimeType($avatarPath, $avatarBytes), base64_encode($avatarBytes));
		} catch (\Throwable) {
			return '';
		} finally {
			@unlink($tempFile);
		}
	}

	/**
	 * @param object $api
	 * @param array<string, mixed> $self
	 * @return array<string, mixed>|null
	 */
	private function fetchLatestUserPhoto(object $api, array $self): ?array {
		$userId = $self['id'] ?? 'me';
		try {
			/** @var array<string, mixed> $photos */
			$photos = $api->photos->getUserPhotos(user_id: $userId, offset: 0, max_id: 0, limit: 1);
			$firstPhoto = $photos['photos'][0] ?? null;
			if (is_array($firstPhoto)) {
				return $firstPhoto;
			}
		} catch (\Throwable) {
			// Fall back to no avatar when the API does not return photo objects.
		}

		return null;
	}

	private function detectMimeType(string $filePath, string $contents): string {
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$mimeType = $finfo->buffer($contents);
		if (is_string($mimeType) && $mimeType !== '') {
			return $mimeType;
		}

		$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
		return match ($extension) {
			'png' => 'image/png',
			'webp' => 'image/webp',
			default => 'image/jpeg',
		};
	}
}
