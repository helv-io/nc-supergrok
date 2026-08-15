<?php

declare(strict_types=1);

namespace OCA\SuperGrok\Service;

use OCA\SuperGrok\AppConstants;
use OCA\SuperGrok\Exception\GrokOAuthException;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use Psr\Log\LoggerInterface;

/**
 * SuperGrok OAuth against auth.x.ai (device code + auth-code PKCE).
 * Grant types and constants match helv-io/ha-supergrok (MIT).
 */
class OAuthService {
	public function __construct(
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return array{0: string, 1: string} [code_verifier, S256 code_challenge]
	 */
	public function generatePkce(int $length = 128): array {
		$length = min(max($length, 43), 128);
		$verifier = substr(rtrim(strtr(base64_encode(random_bytes(96)), '+/', '-_'), '='), 0, $length);
		$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
		return [$verifier, $challenge];
	}

	public function buildAuthorizeUrl(string $codeChallenge, string $state): string {
		return AppConstants::OAUTH_AUTHORIZE_URL . '?' . http_build_query([
			'response_type' => 'code',
			'client_id' => AppConstants::OAUTH_CLIENT_ID,
			'redirect_uri' => AppConstants::OAUTH_REDIRECT_URI,
			'scope' => AppConstants::OAUTH_SCOPE,
			'state' => $state,
			'code_challenge' => $codeChallenge,
			'code_challenge_method' => 'S256',
		], '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * Parse a pasted callback URL, query string, or bare code.
	 *
	 * @return array{0: ?string, 1: ?string, 2: ?string} [code, state, error]
	 */
	public function parseAuthorizationCallback(string $value): array {
		$raw = trim($value);
		if ($raw === '') {
			return [null, null, null];
		}

		$query = '';
		$lowered = strtolower($raw);
		if (str_starts_with($lowered, 'http://') || str_starts_with($lowered, 'https://')) {
			$parts = parse_url($raw);
			$query = (string)($parts['query'] ?? '');
			if ($query === '' && isset($parts['fragment'])) {
				$query = (string)$parts['fragment'];
			}
		} elseif (str_contains($raw, 'code=') || str_contains($raw, 'error=')) {
			$split = explode('?', $raw, 2);
			$query = $split[1] ?? $split[0];
		} else {
			return [$raw, null, null];
		}

		parse_str($query, $params);
		$code = isset($params['code']) && is_string($params['code']) ? $params['code'] : null;
		$state = isset($params['state']) && is_string($params['state']) ? $params['state'] : null;
		$error = isset($params['error']) && is_string($params['error']) ? $params['error'] : null;
		return [$code, $state, $error];
	}

	public function exchangeAuthorizationCode(string $code, string $codeVerifier): OAuthTokens {
		[$status, $payload] = $this->postForm(AppConstants::OAUTH_TOKEN_URL, [
			'grant_type' => 'authorization_code',
			'client_id' => AppConstants::OAUTH_CLIENT_ID,
			'code' => $code,
			'code_verifier' => $codeVerifier,
			'redirect_uri' => AppConstants::OAUTH_REDIRECT_URI,
		]);

		if ($status >= 400) {
			$error = (string)($payload['error'] ?? ('http_' . $status));
			$details = (string)($payload['error_description'] ?? $error);
			$this->logger->warning('Authorization-code exchange failed (' . $status . '): ' . substr($details, 0, 200), [
				'app' => AppConstants::APP_ID,
			]);
			if (in_array($error, ['access_denied', 'authorization_denied'], true)) {
				throw new GrokOAuthException('access_denied', $details);
			}
			if ($status === 403) {
				throw new GrokOAuthException('tier_blocked', $details);
			}
			throw new GrokOAuthException('oauth_failed', $details);
		}

		$this->logger->info('SuperGrok browser login succeeded', ['app' => AppConstants::APP_ID]);
		return $this->tokensFromPayload($payload);
	}

	public function requestDeviceAuthorization(): DeviceAuthorization {
		[$status, $payload] = $this->postForm(AppConstants::OAUTH_DEVICE_URL, [
			'client_id' => AppConstants::OAUTH_CLIENT_ID,
			'scope' => AppConstants::OAUTH_SCOPE,
		]);

		if ($status >= 400) {
			$details = (string)($payload['error_description'] ?? $payload['error'] ?? ('http_' . $status));
			$this->logger->warning('Device authorization failed (' . $status . '): ' . substr($details, 0, 300), [
				'app' => AppConstants::APP_ID,
			]);
			throw new GrokOAuthException('cannot_connect', substr($details, 0, 200));
		}

		$deviceCode = $payload['device_code'] ?? null;
		$userCode = $payload['user_code'] ?? null;
		$verificationUri = $payload['verification_uri'] ?? $payload['verification_url'] ?? null;
		if (!is_string($deviceCode) || !is_string($userCode) || !is_string($verificationUri)) {
			throw new GrokOAuthException('oauth_error', 'Device authorization response incomplete');
		}
		$complete = $payload['verification_uri_complete'] ?? $verificationUri;
		return new DeviceAuthorization(
			$deviceCode,
			$userCode,
			$verificationUri,
			is_string($complete) ? $complete : $verificationUri,
			(int)($payload['expires_in'] ?? 900),
			max((int)($payload['interval'] ?? 5), 1),
		);
	}

	/**
	 * One token-endpoint poll. The settings UI drives the interval.
	 *
	 * @return array{status: string, tokens?: OAuthTokens, interval?: int, reason?: string, message?: string}
	 */
	public function pollDeviceTokenOnce(DeviceAuthorization $device): array {
		[$status, $payload] = $this->postForm(AppConstants::OAUTH_TOKEN_URL, [
			'grant_type' => AppConstants::OAUTH_DEVICE_GRANT,
			'client_id' => AppConstants::OAUTH_CLIENT_ID,
			'device_code' => $device->deviceCode,
		]);

		$error = isset($payload['error']) && is_string($payload['error']) ? $payload['error'] : null;
		if ($status < 400 && isset($payload['access_token'])) {
			$this->logger->info('SuperGrok device login succeeded', ['app' => AppConstants::APP_ID]);
			return ['status' => 'ok', 'tokens' => $this->tokensFromPayload($payload)];
		}
		if (in_array($error, ['authorization_pending', 'slow_down'], true)) {
			$result = ['status' => $error];
			if ($error === 'slow_down') {
				$result['interval'] = min($device->interval + 5, 30);
			}
			return $result;
		}
		if (in_array($error, ['access_denied', 'authorization_denied'], true)) {
			throw new GrokOAuthException('access_denied', (string)($payload['error_description'] ?? $error));
		}
		if ($error === 'expired_token') {
			throw new GrokOAuthException('oauth_timeout', 'Device code expired');
		}
		$this->logger->warning('Unexpected device-token response (' . $status . '): ' . substr((string)json_encode($payload), 0, 300), [
			'app' => AppConstants::APP_ID,
		]);
		throw new GrokOAuthException('oauth_failed', (string)($payload['error_description'] ?? $error ?? $status));
	}

	public function refreshTokens(OAuthTokens $tokens): OAuthTokens {
		[$status, $payload] = $this->postForm(AppConstants::OAUTH_TOKEN_URL, [
			'grant_type' => 'refresh_token',
			'client_id' => AppConstants::OAUTH_CLIENT_ID,
			'refresh_token' => $tokens->refreshToken,
		]);

		if ($status === 403) {
			throw new GrokOAuthException(
				'tier_blocked',
				(string)($payload['error_description'] ?? 'This SuperGrok account is not entitled to the OAuth API surface'),
			);
		}
		if ($status >= 400) {
			$error = (string)($payload['error'] ?? ('http_' . $status));
			if ($error === 'invalid_grant' || in_array($status, [400, 401], true)) {
				throw new GrokOAuthException('reauth_required', (string)($payload['error_description'] ?? $error));
			}
			throw new GrokOAuthException('oauth_failed', (string)($payload['error_description'] ?? $error));
		}
		return $this->tokensFromPayload($payload, $tokens);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function fetchUserinfo(string $accessToken): array {
		try {
			$response = $this->request('GET', AppConstants::OAUTH_USERINFO_URL, [
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
					'Accept' => 'application/json',
					'User-Agent' => AppConstants::USER_AGENT,
				],
				'timeout' => 20,
				'http_errors' => false,
			]);
		} catch (\Throwable $e) {
			$this->logger->debug('userinfo failed: ' . $e->getMessage(), ['app' => AppConstants::APP_ID]);
			return [];
		}
		if ($response->getStatusCode() >= 400) {
			$this->logger->debug('userinfo failed (' . $response->getStatusCode() . ')', ['app' => AppConstants::APP_ID]);
			return [];
		}
		$data = $this->decodeJson($this->bodyToString($response->getBody()));
		return is_array($data) ? $data : [];
	}

	public function revoke(string $token): void {
		try {
			$this->postForm(AppConstants::OAUTH_REVOKE_URL, [
				'token' => $token,
				'client_id' => AppConstants::OAUTH_CLIENT_ID,
			]);
		} catch (\Throwable $e) {
			$this->logger->warning('SuperGrok token revoke failed: ' . $e->getMessage(), [
				'app' => AppConstants::APP_ID,
			]);
		}
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public function tokensFromPayload(array $payload, ?OAuthTokens $previous = null): OAuthTokens {
		$access = $payload['access_token'] ?? null;
		$refresh = $payload['refresh_token'] ?? ($previous?->refreshToken);
		if (!is_string($access) || $access === '' || !is_string($refresh) || $refresh === '') {
			throw new GrokOAuthException('oauth_error', 'Token response missing access or refresh token');
		}
		$idToken = $payload['id_token'] ?? ($previous?->idToken);
		return new OAuthTokens(
			$access,
			$refresh,
			$this->expiresAtFromPayload($payload),
			is_string($idToken) ? $idToken : null,
			(string)($payload['token_type'] ?? 'Bearer'),
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function expiresAtFromPayload(array $payload): int {
		if (isset($payload['expires_in'])) {
			try {
				return time() + (int)$payload['expires_in'];
			} catch (\Throwable $e) {
				// Fall through to JWT exp.
			}
		}
		$token = (string)($payload['access_token'] ?? '');
		$parts = explode('.', $token);
		if (count($parts) === 3) {
			$padded = $parts[1] . str_repeat('=', (4 - strlen($parts[1]) % 4) % 4);
			$json = base64_decode(strtr($padded, '-_', '+/'), true);
			if (is_string($json) && $json !== '') {
				$claims = json_decode($json, true);
				if (is_array($claims) && isset($claims['exp'])) {
					return (int)$claims['exp'];
				}
			}
		}
		return time() + 900;
	}

	/**
	 * @param array<string, string> $data
	 * @return array{0: int, 1: array<string, mixed>}
	 */
	private function postForm(string $url, array $data): array {
		$response = $this->request('POST', $url, [
			'headers' => [
				'Accept' => 'application/json',
				'Content-Type' => 'application/x-www-form-urlencoded',
				'User-Agent' => AppConstants::USER_AGENT,
			],
			'body' => http_build_query($data),
			'timeout' => 30,
			'http_errors' => false,
		]);
		$body = $this->bodyToString($response->getBody());
		$decoded = $this->decodeJson($body);
		if (!is_array($decoded)) {
			$decoded = ['error' => $body];
		}
		return [$response->getStatusCode(), $decoded];
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private function request(string $method, string $url, array $options): IResponse {
		$client = $this->clientService->newClient();
		try {
			if ($method === 'GET') {
				return $client->get($url, $options);
			}
			return $client->post($url, $options);
		} catch (\Throwable $e) {
			$response = $this->responseFromException($e);
			if ($response !== null) {
				return $response;
			}
			throw new GrokOAuthException('cannot_connect', $e->getMessage(), $e);
		}
	}

	private function responseFromException(\Throwable $e): ?IResponse {
		if (method_exists($e, 'getResponse')) {
			$response = $e->getResponse();
			if ($response instanceof IResponse) {
				return $response;
			}
		}
		return null;
	}

	private function bodyToString(mixed $body): string {
		if (is_resource($body)) {
			$contents = stream_get_contents($body);
			return is_string($contents) ? $contents : '';
		}
		return (string)$body;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function decodeJson(string $body): ?array {
		if ($body === '') {
			return [];
		}
		$decoded = json_decode($body, true);
		return is_array($decoded) ? $decoded : null;
	}
}
