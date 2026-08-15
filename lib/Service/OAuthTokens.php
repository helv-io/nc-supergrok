<?php

declare(strict_types=1);

namespace OCA\SuperGrok\Service;

use OCA\SuperGrok\AppConstants;

/**
 * Access token plus rotating refresh token.
 */
class OAuthTokens {
	public function __construct(
		public string $accessToken,
		public string $refreshToken,
		public int $expiresAt,
		public ?string $idToken = null,
		public string $tokenType = 'Bearer',
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function asArray(): array {
		$data = [
			'access_token' => $this->accessToken,
			'refresh_token' => $this->refreshToken,
			'expires_at' => $this->expiresAt,
			'token_type' => $this->tokenType,
		];
		if ($this->idToken !== null && $this->idToken !== '') {
			$data['id_token'] = $this->idToken;
		}
		return $data;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray(array $data): self {
		return new self(
			(string)$data['access_token'],
			(string)$data['refresh_token'],
			(int)($data['expires_at'] ?? 0),
			isset($data['id_token']) ? (string)$data['id_token'] : null,
			(string)($data['token_type'] ?? 'Bearer'),
		);
	}

	public function isExpired(int $skew = AppConstants::TOKEN_EXPIRY_SKEW_SECONDS): bool {
		if ($this->expiresAt <= 0) {
			return true;
		}
		return time() >= ($this->expiresAt - $skew);
	}
}
