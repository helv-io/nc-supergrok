<?php

declare(strict_types=1);

namespace OCA\SuperGrok\Service;

use OCA\SuperGrok\AppConstants;
use OCP\Security\ICredentialsManager;
use Psr\Log\LoggerInterface;

/**
 * Per-user SuperGrok tokens via ICredentialsManager (encrypted at rest).
 * Identifier is supergrok_oauth. userId '' is the admin/instance slot.
 */
class TokenStore {
	public function __construct(
		private ICredentialsManager $credentialsManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param array<string, mixed> $account
	 */
	public function saveTokens(string $userId, OAuthTokens $tokens, array $account = []): void {
		$existing = $this->retrieveRaw($userId);
		$blob = [
			'access_token' => $tokens->accessToken,
			'refresh_token' => $tokens->refreshToken,
			'expires_at' => $tokens->expiresAt,
			'token_type' => $tokens->tokenType,
			'id_token' => $tokens->idToken,
			'account_id' => $this->stringField($account, 'sub', $existing['account_id'] ?? ''),
			'account_email' => $this->stringField($account, 'email', $existing['account_email'] ?? ''),
			'account_name' => $this->stringField($account, 'name', $existing['account_name'] ?? ''),
		];
		$this->credentialsManager->store($userId, AppConstants::CREDENTIALS_IDENTIFIER, $blob);
	}

	public function getTokens(string $userId): ?OAuthTokens {
		$raw = $this->retrieveRaw($userId);
		if ($raw === null) {
			return null;
		}
		$access = (string)($raw['access_token'] ?? '');
		$refresh = (string)($raw['refresh_token'] ?? '');
		if ($access === '' || $refresh === '') {
			return null;
		}
		$idToken = isset($raw['id_token']) && is_string($raw['id_token']) && $raw['id_token'] !== ''
			? $raw['id_token']
			: null;
		return new OAuthTokens(
			$access,
			$refresh,
			(int)($raw['expires_at'] ?? 0),
			$idToken,
			(string)($raw['token_type'] ?? 'Bearer'),
		);
	}

	public function hasSession(string $userId): bool {
		return $this->getTokens($userId) !== null
			|| ($userId !== '' && $this->getTokens('') !== null);
	}

	/**
	 * Safe for the browser. Never includes tokens.
	 *
	 * @return array{signedIn: bool, email: string, name: string}
	 */
	public function getPublicAccount(string $userId): array {
		$raw = $this->retrieveRaw($userId);
		if ($raw === null && $userId !== '') {
			$raw = $this->retrieveRaw('');
		}
		if ($raw === null) {
			return ['signedIn' => false, 'email' => '', 'name' => ''];
		}
		return [
			'signedIn' => true,
			'email' => (string)($raw['account_email'] ?? ''),
			'name' => (string)($raw['account_name'] ?? ''),
		];
	}

	public function clear(string $userId): void {
		$this->credentialsManager->delete($userId, AppConstants::CREDENTIALS_IDENTIFIER);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function retrieveRaw(string $userId): ?array {
		try {
			$raw = $this->credentialsManager->retrieve($userId, AppConstants::CREDENTIALS_IDENTIFIER);
		} catch (\Throwable $e) {
			$this->logger->warning('SuperGrok credential read failed: ' . $e->getMessage(), [
				'app' => AppConstants::APP_ID,
			]);
			return null;
		}
		return is_array($raw) ? $raw : null;
	}

	/**
	 * @param array<string, mixed> $account
	 */
	private function stringField(array $account, string $key, string $fallback): string {
		$value = $account[$key] ?? null;
		return is_string($value) && $value !== '' ? $value : $fallback;
	}
}
