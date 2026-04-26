<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Christoph Wurst <christoph@winzerhof-wurst.at>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorGateway\Provider\Channel\Signal;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\PlainTextRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use OCA\TwoFactorGateway\Exception\MessageTransmissionException;
use OCA\TwoFactorGateway\Provider\FieldDefinition;
use OCA\TwoFactorGateway\Provider\Gateway\AGateway;
use OCA\TwoFactorGateway\Provider\Gateway\IInteractiveSetupGateway;
use OCA\TwoFactorGateway\Provider\Gateway\ITestResultEnricher;
use OCA\TwoFactorGateway\Provider\Settings;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * An integration of https://gitlab.com/morph027/signal-web-gateway and
 * https://github.com/bbernhard/signal-cli-rest-api with interactive device-link setup.
 *
 * @method string getUrl()
 * @method AGateway setUrl(string $url)
 * @method string getAccount()
 * @method AGateway setAccount(string $account)
 */
class Gateway extends AGateway implements IInteractiveSetupGateway, ITestResultEnricher {
	public const ACCOUNT_UNNECESSARY = 'unneccessary';

	private InteractiveSetupStateStore $interactiveSetupStateStore;

	public function __construct(
		public IAppConfig $appConfig,
		private IClientService $clientService,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
		?InteractiveSetupStateStore $interactiveSetupStateStore = null,
	) {
		parent::__construct($appConfig);
		$this->interactiveSetupStateStore = $interactiveSetupStateStore ?? new InteractiveSetupStateStore($appConfig);
	}

	#[\Override]
	public function createSettings(): Settings {
		return new Settings(
			name: 'Signal',
			instructions: 'The gateway can send authentication to your Signal mobile and deskop app.',
			fields: [
				new FieldDefinition(
					field: 'url',
					prompt: 'Please enter the URL of the Signal gateway (leave blank to use default):',
					default: 'http://localhost:5000',
				),
				new FieldDefinition(
					field: 'account',
					prompt: 'Please enter the account (phone-number) of the sending signal account (leave blank if a phone-number is not required):',
				),
			]
		);
	}

	#[\Override]
	public function send(string $identifier, string $message, array $extra = []): void {
		$client = $this->clientService->newClient();

		// look for 6 digits to hide the OTP code in the message
		$message = preg_replace('/(\d{6})/', '||$1||', $message);
		
		// determine type of gateway

		// test for native signal-cli JSON RPC.
		$response = $client->post(
			$this->getUrl() . '/api/v1/rpc',
			[
				'http_errors' => false,
				'json' => [
					'jsonrpc' => '2.0',
					'method' => 'version',
					'id' => 'version_' . $this->timeFactory->getTime(),
				],
			]);
		if ($response->getStatusCode() === 200 || $response->getStatusCode() === 201) {
			// native signal-cli JSON RPC.

			// Groups have to be detected and passed with the "group-id" parameter. We assume a group is given as base64 encoded string
			$groupId = base64_decode($identifier, strict: true);
			$isGroup = $groupId !== false && base64_encode($groupId) === $identifier;
			$recipientKey = $isGroup ? 'group-id' : 'recipient';
			$params = [
				'message' => $message,
				$recipientKey => $identifier,
				'account' => $this->getAccount(), // mandatory for native RPC API
			];
			$response = $response = $client->post(
				$this->getUrl() . '/api/v1/rpc',
				[
					'json' => [
						'jsonrpc' => '2.0',
						'method' => 'send',
						'id' => 'code_' . $this->timeFactory->getTime(),
						'params' => $params,
					],
				]);
			$body = $response->getBody();
			$json = json_decode($body, true);
			$statusCode = $response->getStatusCode();
			// The 201 "created" is probably a bug.
			if ($statusCode < 200 || $statusCode >= 300 || is_null($json) || !is_array($json) || ($json['jsonrpc'] ?? null) != '2.0' || !isset($json['result']['timestamp'])) {
				throw new MessageTransmissionException("error reported by Signal gateway, status=$statusCode, body=$body}");
			}
		} else {
			// Try gateway in the style of https://gitlab.com/morph027/signal-cli-dbus-rest-api
			$response = $client->get(
				$this->getUrl() . '/v1/about',
				[
					'http_errors' => false,
				]
			);
			if ($response->getStatusCode() === 200) {
				// Not so "ńew style" gateway, see
				// https://gitlab.com/morph027/signal-cli-dbus-rest-api
				// https://gitlab.com/morph027/python-signal-cli-rest-api
				// https://github.com/bbernhard/signal-cli-rest-api
				$body = $response->getBody();
				$json = json_decode($body, true);
				$versions = $json['versions'] ?? [];
				if (is_array($versions) && in_array('v2', $versions)) {
					$json = [
						// signal-cli-rest-api v2 expects recipients as a string array.
						'recipients' => [$identifier],
						'message' => $message, // add styling
						'text_mode' => 'styled',
					];
					$account = $this->getAccount();
					if ($account != self::ACCOUNT_UNNECESSARY) {
						$json['number'] = $account;
					}
					$response = $client->post(
						$this->getUrl() . '/v2/send',
						[
							'http_errors' => false,
							'json' => $json,
						]
					);
				} else {
					$response = $client->post(
						$this->getUrl() . '/v1/send/' . $identifier,
						[
							'http_errors' => false,
							'json' => [ 'message' => $message ],
						]
					);
				}
				$body = (string)$response->getBody();
				$json = json_decode($body, true);
				$status = $response->getStatusCode();
				if ($status !== 201 || is_null($json) || !is_array($json) || !isset($json['timestamp'])) {
					throw new MessageTransmissionException("error reported by Signal gateway, status=$status, body=$body}");
				}
			} else {
				// Try old deprecated gateway https://gitlab.com/morph027/signal-web-gateway
				$response = $client->post(
					$this->getUrl() . '/v1/send/' . $identifier,
					[
						'http_errors' => false,
						'body' => [
							'to' => $identifier,
							'message' => $message,
						],
						'json' => [ 'message' => $message ],
					]
				);
				$body = (string)$response->getBody();
				$json = json_decode($body, true);

				$status = $response->getStatusCode();
				if ($status !== 200 || is_null($json) || !is_array($json) || !isset($json['success']) || $json['success'] !== true) {
					throw new MessageTransmissionException("error reported by Signal gateway, status=$status, body=$body}");
				}
			}
		}
	}

	#[\Override]
	public function cliConfigure(InputInterface $input, OutputInterface $output): int {
		$settings = $this->getSettings();
		$helper = new QuestionHelper();
		$urlQuestion = new Question($settings->fields[0]->prompt, $settings->fields[0]->default);
		$url = $helper->ask($input, $output, $urlQuestion);
		$output->writeln("Using $url.");

		$this->setUrl($url);

		// Check if this is a signal-cli-rest-api instance with the device-link capability
		$apiStyle = $this->detectRestApiStyle($url);

		if ($apiStyle === 'signal-cli-rest-api') {
			return $this->cliConfigureWithDeviceLink($input, $output, $url);
		}

		// Fallback: manual account entry for other gateway styles
		$accountQuestion = new Question($settings->fields[1]->prompt, $settings->fields[1]->default);
		$account = $helper->ask($input, $output, $accountQuestion);
		if ($account == '') {
			$account = self::ACCOUNT_UNNECESSARY;
			$output->writeln('A signal account is not needed, assuming it is hardcoded into the signal gateway server.');
		} else {
			$output->writeln("Using $account.");
		}

		$this->setAccount($account);

		return 0;
	}

	private function cliConfigureWithDeviceLink(InputInterface $input, OutputInterface $output, string $url): int {
		$client = $this->clientService->newClient();

		// Check if already registered
		try {
			$accountsResponse = $client->get($url . '/v1/accounts', ['http_errors' => false]);
			$accounts = json_decode((string)$accountsResponse->getBody(), true) ?? [];
			if (is_array($accounts) && $accounts !== []) {
				$output->writeln('<info>Found existing registered account(s):</info>');
				foreach ($accounts as $acc) {
					$output->writeln('  - ' . $acc);
				}
				$account = (string)reset($accounts);
				$accountMeta = $this->fetchAccountMetadata($url, $account, false);
				$this->setAccount($account);
				$output->writeln('<info>✓ Using account ' . $account . '</info>');
				if (($accountMeta['account_name'] ?? '') !== '' && $accountMeta['account_name'] !== $account) {
					$output->writeln('<info>  Name: ' . $accountMeta['account_name'] . '</info>');
				}
				return 0;
			}
		} catch (\Throwable $e) {
			$this->logger->debug('Could not fetch Signal accounts during CLI setup', ['exception' => $e]);
		}

		// Fetch device link URI for QR display
		$output->writeln('<info>No registered accounts found. Fetching device link for QR pairing…</info>');

		try {
			$qrRawResponse = $client->get(
				$url . '/v1/qrcodelink/raw',
				['http_errors' => false, 'query' => ['device_name' => 'NextcloudGateway']],
			);

			if ($qrRawResponse->getStatusCode() !== 200) {
				$output->writeln('<error>Could not get device link from Signal gateway: ' . $qrRawResponse->getStatusCode() . '</error>');
				return 1;
			}

			$payload = json_decode((string)$qrRawResponse->getBody(), true);
			$deviceLinkUri = (string)($payload['device_link_uri'] ?? '');
		} catch (\Throwable $e) {
			$output->writeln('<error>Could not connect to Signal gateway: ' . $e->getMessage() . '</error>');
			return 1;
		}

		if ($deviceLinkUri === '') {
			$output->writeln('<error>Signal gateway returned an empty device link URI.</error>');
			return 1;
		}

		// Display QR code in terminal
		$output->writeln('');
		$output->writeln('<info>Scan the QR code below with your Signal app to link this device:</info>');
		$output->writeln('<info>(Settings → Linked devices → Link new device)</info>');
		$output->writeln('');

		$this->printTerminalQr($output, $deviceLinkUri);

		$output->writeln('');
		$output->writeln('<comment>Or open this link directly in Signal:</comment>');
		$output->writeln($deviceLinkUri);
		$output->writeln('');

		// Poll until account appears
		$output->writeln('<info>Waiting for Signal device link confirmation…</info>');
		$maxAttempts = 60;
		$account = null;

		for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
			sleep(3);
			try {
				$accountsResponse = $client->get($url . '/v1/accounts', ['http_errors' => false]);
				$accounts = json_decode((string)$accountsResponse->getBody(), true) ?? [];
				if (is_array($accounts) && $accounts !== []) {
					$account = (string)reset($accounts);
					break;
				}
			} catch (\Throwable $e) {
				$this->logger->debug('Polling for Signal accounts failed', ['exception' => $e]);
			}

			if ($attempt % 5 === 4) {
				$output->write('.');
			}
		}

		$output->writeln('');

		if ($account === null) {
			$output->writeln('<error>Timed out waiting for Signal device link. Please try again.</error>');
			return 1;
		}

		$accountMeta = $this->fetchAccountMetadata($url, $account, false);
		$this->setAccount($account);
		$output->writeln('<info>✓ Successfully linked! Using account: ' . $account . '</info>');
		if (($accountMeta['account_name'] ?? '') !== '' && $accountMeta['account_name'] !== $account) {
			$output->writeln('<info>  Name: ' . $accountMeta['account_name'] . '</info>');
		}
		return 0;
	}

	private function printTerminalQr(OutputInterface $output, string $content): void {
		try {
			$writer = new Writer(new PlainTextRenderer(2));
			$qrText = $writer->writeString($content);
			$output->writeln($qrText);
		} catch (\Throwable $e) {
			$this->logger->debug('Could not render terminal QR code', ['exception' => $e]);
		}
	}

	// ─── IInteractiveSetupGateway ─────────────────────────────────────────────

	/**
	 * @param array<string, string> $input
	 * @return array<string, mixed>
	 */
	#[\Override]
	public function interactiveSetupStart(array $input): array {
		$url = rtrim(trim((string)($input['url'] ?? '')), '/');
		if ($url === '') {
			return $this->withMessageType([
				'status' => 'error',
				'message' => 'Signal gateway URL is required to start interactive setup.',
			]);
		}

		$apiStyle = $this->detectRestApiStyle($url);
		if ($apiStyle !== 'signal-cli-rest-api') {
			return $this->withMessageType([
				'status' => 'error',
				'message' => 'Interactive QR setup requires a signal-cli-rest-api compatible gateway (bbernhard/signal-cli-rest-api). The provided URL does not appear to be one.',
			]);
		}

		// Check if already has a registered account
		$accounts = $this->fetchAccounts($url);
		if ($accounts !== null && $accounts !== []) {
			$account = reset($accounts);
			$accountMeta = $this->fetchAccountMetadata($url, (string)$account, false);
			$sessionId = $this->interactiveSetupStateStore->createSessionId();
			$this->interactiveSetupStateStore->save($sessionId, ['url' => $url]);
			$this->interactiveSetupStateStore->delete($sessionId);
			$message = 'Signal gateway already has a registered account: ' . $account;
			if (($accountMeta['account_name'] ?? '') !== '' && $accountMeta['account_name'] !== $account) {
				$message .= ' | Name: ' . $accountMeta['account_name'];
			}
			return $this->withMessageType([
				'status' => 'done',
				'message' => $message,
				'config' => array_merge(['url' => $url, 'account' => $account], $accountMeta),
			]);
		}

		// Fetch QR code SVG server-side (URL is internal, not reachable from browser)
		$qrSvg = $this->fetchQrSvg($url);
		if ($qrSvg === null) {
			return $this->withMessageType([
				'status' => 'error',
				'message' => 'Could not retrieve QR code from Signal gateway. Verify the URL and try again.',
			]);
		}

		$sessionId = $this->interactiveSetupStateStore->createSessionId();
		$this->interactiveSetupStateStore->save($sessionId, ['url' => $url]);

		return $this->withMessageType([
			'status' => 'pending',
			'sessionId' => $sessionId,
			'step' => 'scan_qr',
			'message' => 'Scan the QR code with your Signal app to link this gateway. Go to Settings → Linked Devices → Link New Device.',
			'data' => [
				'qr_svg' => $qrSvg,
			],
		]);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	#[\Override]
	public function interactiveSetupStep(string $sessionId, string $action, array $input = []): array {
		$state = $this->interactiveSetupStateStore->load($sessionId);
		if ($state === null) {
			return $this->withMessageType([
				'status' => 'error',
				'message' => 'Setup session not found or expired. Please restart setup.',
			]);
		}

		return $this->withMessageType(match ($action) {
			'poll_link' => $this->interactiveSetupPollLink($sessionId, $state),
			'cancel' => $this->interactiveSetupCancel($sessionId),
			default => [
				'status' => 'error',
				'message' => 'Unknown setup action: ' . $action,
			],
		});
	}

	/** @return array<string, mixed> */
	#[\Override]
	public function interactiveSetupCancel(string $sessionId): array {
		$this->interactiveSetupStateStore->delete($sessionId);
		return $this->withMessageType([
			'status' => 'cancelled',
			'message' => 'Interactive setup cancelled.',
		]);
	}

	// ─── ITestResultEnricher ──────────────────────────────────────────────────

	/**
	 * @param array<string, string> $instanceConfig
	 * @return array<string, string>
	 */
	#[\Override]
	public function enrichTestResult(array $instanceConfig, string $identifier = ''): array {
		$url = rtrim(trim((string)($instanceConfig['url'] ?? $this->getUrl())), '/');
		$accounts = $this->fetchAccounts($url);
		if ($accounts === null || $accounts === []) {
			return [];
		}

		$account = (string)reset($accounts);
		$accountMeta = $this->fetchAccountMetadata($url, $account);
		if (($accountMeta['account_name'] ?? '') === '') {
			$accountMeta['account_name'] = $account;
		}

		return $accountMeta;
	}

	// ─── Private helpers ──────────────────────────────────────────────────────

	/**
	 * @param array<string, mixed> $state
	 * @return array<string, mixed>
	 */
	private function interactiveSetupPollLink(string $sessionId, array $state): array {
		$url = (string)($state['url'] ?? '');
		$accounts = $this->fetchAccounts($url);

		if ($accounts !== null && $accounts !== []) {
			$account = reset($accounts);
			$accountMeta = $this->fetchAccountMetadata($url, (string)$account, false);
			$message = 'Signal device linked successfully. Account: ' . $account;
			if (($accountMeta['account_name'] ?? '') !== '' && $accountMeta['account_name'] !== $account) {
				$message .= ' | Name: ' . $accountMeta['account_name'];
			}
			$this->interactiveSetupStateStore->delete($sessionId);
			return [
				'status' => 'done',
				'message' => $message,
				'config' => array_merge(['url' => $url, 'account' => $account], $accountMeta),
			];
		}

		// Do NOT fetch a new QR here: each call to /v1/qrcodelink/raw generates a fresh
		// key-pair and invalidates the previous device-link URI. The QR already shown in
		// the browser remains valid until the user scans it; we only need to wait for the
		// account to appear.
		return [
			'status' => 'pending',
			'sessionId' => $sessionId,
			'step' => 'scan_qr',
			'message' => 'Not linked yet. Keep the QR code open and scan it with your Signal app.',
		];
	}

	/** @return list<string>|null  null on HTTP/network error */
	private function fetchAccounts(string $url): ?array {
		try {
			$client = $this->clientService->newClient();
			$response = $client->get($url . '/v1/accounts', ['http_errors' => false]);
			if ($response->getStatusCode() !== 200) {
				return null;
			}

			$accounts = json_decode((string)$response->getBody(), true);
			return is_array($accounts) ? array_values(array_filter($accounts, 'is_string')) : null;
		} catch (\Throwable $e) {
			$this->logger->debug('Could not fetch Signal accounts', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * @return array{account_name?: string, account_avatar_url?: string}
	 */
	private function fetchAccountMetadata(string $url, string $account, bool $includeAvatar = true): array {
		$account = trim($account);
		if ($account === '') {
			return [];
		}

		$contact = $this->fetchContactInfo($url, $account);
		$accountName = $this->extractAccountNameFromContact($contact, $account);
		$result = ['account_name' => $accountName];

		if ($includeAvatar) {
			$avatarDataUri = $this->fetchOwnAvatarDataUri($url, $account);
			if ($avatarDataUri !== '') {
				$result['account_avatar_url'] = $avatarDataUri;
			}
		}

		return $result;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function fetchContactInfo(string $url, string $account): ?array {
		try {
			$client = $this->clientService->newClient();
			$encodedAccount = rawurlencode($account);
			$response = $client->get(
				$url . '/v1/contacts/' . $encodedAccount . '/' . $encodedAccount,
				[
					'http_errors' => false,
					'query' => ['all_recipients' => 'true'],
				],
			);
			if ($response->getStatusCode() !== 200) {
				return null;
			}

			$contact = json_decode((string)$response->getBody(), true);
			return is_array($contact) ? $contact : null;
		} catch (\Throwable $e) {
			$this->logger->debug('Could not fetch Signal contact metadata', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * @param array<string, mixed>|null $contact
	 */
	private function extractAccountNameFromContact(?array $contact, string $fallbackAccount): string {
		if ($contact === null) {
			return $fallbackAccount;
		}

		$profile = (isset($contact['profile']) && is_array($contact['profile'])) ? $contact['profile'] : [];
		$profileGivenName = trim((string)($profile['given_name'] ?? ''));
		$profileLastName = trim((string)($profile['lastname'] ?? ''));
		$profileFullName = trim($profileGivenName . ' ' . $profileLastName);

		$candidates = [
			trim((string)($contact['name'] ?? '')),
			trim((string)($contact['profile_name'] ?? '')),
			$profileFullName,
			$profileGivenName,
			trim((string)($contact['given_name'] ?? '')),
			trim((string)($contact['username'] ?? '')),
		];

		foreach ($candidates as $candidate) {
			if ($candidate !== '') {
				return $candidate;
			}
		}

		return $fallbackAccount;
	}

	private function fetchOwnAvatarDataUri(string $url, string $account): string {
		try {
			$client = $this->clientService->newClient();
			$encodedAccount = rawurlencode($account);
			$response = $client->get(
				$url . '/v1/contacts/' . $encodedAccount . '/' . $encodedAccount . '/avatar',
				['http_errors' => false],
			);
			if ($response->getStatusCode() !== 200) {
				return '';
			}

			$binary = (string)$response->getBody();
			if ($binary === '') {
				return '';
			}

			$mimeType = 'image/jpeg';
			if (function_exists('finfo_buffer')) {
				$finfo = @finfo_open(FILEINFO_MIME_TYPE);
				if ($finfo !== false) {
					$detected = finfo_buffer($finfo, $binary);
					finfo_close($finfo);
					if (is_string($detected) && str_starts_with($detected, 'image/')) {
						$mimeType = $detected;
					}
				}
			}

			return 'data:' . $mimeType . ';base64,' . base64_encode($binary);
		} catch (\Throwable $e) {
			$this->logger->debug('Could not fetch Signal contact avatar', ['exception' => $e]);
			return '';
		}
	}

	private function fetchQrSvg(string $url): ?string {
		try {
			$client = $this->clientService->newClient();
			$response = $client->get(
				$url . '/v1/qrcodelink/raw',
				['http_errors' => false, 'query' => ['device_name' => 'NextcloudGateway']],
			);

			if ($response->getStatusCode() !== 200) {
				return null;
			}

			$payload = json_decode((string)$response->getBody(), true);
			$deviceLinkUri = (string)($payload['device_link_uri'] ?? '');
			if ($deviceLinkUri === '') {
				return null;
			}

			return $this->buildSvgQr($deviceLinkUri);
		} catch (\Throwable $e) {
			$this->logger->debug('Could not fetch Signal QR device link', ['exception' => $e]);
			return null;
		}
	}

	private function buildSvgQr(string $content): ?string {
		try {
			$renderer = new ImageRenderer(
				new RendererStyle(300),
				new SvgImageBackEnd(),
			);
			$writer = new Writer($renderer);
			return $writer->writeString($content);
		} catch (\Throwable $e) {
			$this->logger->debug('Could not generate Signal QR SVG', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * Detect if the gateway at $url is a bbernhard/signal-cli-rest-api instance.
	 * Returns 'signal-cli-rest-api', 'native-rpc', or 'unknown'.
	 */
	private function detectRestApiStyle(string $url): string {
		try {
			$client = $this->clientService->newClient();
			$response = $client->get($url . '/v1/about', ['http_errors' => false]);
			if ($response->getStatusCode() === 200) {
				return 'signal-cli-rest-api';
			}

			// Try native JSON-RPC
			$rpcResponse = $client->post(
				$url . '/api/v1/rpc',
				[
					'http_errors' => false,
					'json' => ['jsonrpc' => '2.0', 'method' => 'version', 'id' => 'detect'],
				],
			);
			if ($rpcResponse->getStatusCode() === 200 || $rpcResponse->getStatusCode() === 201) {
				return 'native-rpc';
			}
		} catch (\Throwable $e) {
			$this->logger->debug('Could not detect Signal gateway style', ['exception' => $e]);
		}

		return 'unknown';
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function withMessageType(array $payload): array {
		if (!isset($payload['messageType']) && isset($payload['status'])) {
			$payload['messageType'] = match ((string)$payload['status']) {
				'done' => 'success',
				'error' => 'error',
				'needs_input', 'pending', 'cancelled' => 'info',
				default => 'info',
			};
		}

		return $payload;
	}
}
