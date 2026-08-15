<?php

declare(strict_types=1);

namespace OCA\SuperGrok\Service;

/**
 * RFC 8628 device-code challenge.
 */
class DeviceAuthorization {
	public function __construct(
		public string $deviceCode,
		public string $userCode,
		public string $verificationUri,
		public string $verificationUriComplete,
		public int $expiresIn,
		public int $interval,
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function asArray(): array {
		return [
			'device_code' => $this->deviceCode,
			'user_code' => $this->userCode,
			'verification_uri' => $this->verificationUri,
			'verification_uri_complete' => $this->verificationUriComplete,
			'expires_in' => $this->expiresIn,
			'interval' => $this->interval,
			'expires_at' => time() + $this->expiresIn,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray(array $data): self {
		return new self(
			(string)$data['device_code'],
			(string)($data['user_code'] ?? ''),
			(string)($data['verification_uri'] ?? ''),
			(string)($data['verification_uri_complete'] ?? $data['verification_uri'] ?? ''),
			(int)($data['expires_in'] ?? 900),
			max((int)($data['interval'] ?? 5), 1),
		);
	}
}
