<?php

declare(strict_types=1);

namespace OCA\SuperGrok\Controller;

use OCA\SuperGrok\AppConstants;
use OCA\SuperGrok\Exception\GrokClientException;
use OCA\SuperGrok\Exception\GrokOAuthException;
use OCA\SuperGrok\Service\ClientFactory;
use OCA\SuperGrok\Service\DeviceAuthorization;
use OCA\SuperGrok\Service\OAuthService;
use OCA\SuperGrok\Service\TokenStore;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\ISession;

class OAuthController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private OAuthService $oauthService,
		private TokenStore $tokenStore,
		private ClientFactory $clientFactory,
		private ISession $session,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function status(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['signedIn' => false, 'email' => '', 'name' => '']);
		}
		return new JSONResponse($this->tokenStore->getPublicAccount($this->userId));
	}

	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	public function startDevice(): JSONResponse {
		if ($this->userId === null) {
			return $this->error('not_signed_in', 'Not logged in', Http::STATUS_UNAUTHORIZED);
		}
		try {
			$device = $this->oauthService->requestDeviceAuthorization();
		} catch (GrokOAuthException $e) {
			return $this->error($e->getReason(), $e->getDetails());
		}
		$this->session->set(AppConstants::SESSION_DEVICE, json_encode($device->asArray()));
		return new JSONResponse([
			'user_code' => $device->userCode,
			'verification_uri' => $device->verificationUri,
			'verification_uri_complete' => $device->verificationUriComplete,
			'expires_in' => $device->expiresIn,
			'interval' => $device->interval,
		]);
	}

	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	public function pollDevice(): JSONResponse {
		if ($this->userId === null) {
			return $this->error('not_signed_in', 'Not logged in', Http::STATUS_UNAUTHORIZED);
		}
		$raw = $this->sessionArray(AppConstants::SESSION_DEVICE);
		if ($raw === null) {
			return $this->error('oauth_failed', 'Start device sign-in again');
		}
		$expiresAt = (int)($raw['expires_at'] ?? 0);
		if ($expiresAt > 0 && time() >= $expiresAt) {
			$this->session->remove(AppConstants::SESSION_DEVICE);
			return $this->error('oauth_timeout', 'Device code expired');
		}
		$device = DeviceAuthorization::fromArray($raw);
		try {
			$result = $this->oauthService->pollDeviceTokenOnce($device);
		} catch (GrokOAuthException $e) {
			if (in_array($e->getReason(), ['oauth_timeout', 'access_denied', 'oauth_failed'], true)) {
				$this->session->remove(AppConstants::SESSION_DEVICE);
			}
			return $this->error($e->getReason(), $e->getDetails());
		}
		if (($result['status'] ?? '') === 'ok' && isset($result['tokens'])) {
			$account = $this->oauthService->fetchUserinfo($result['tokens']->accessToken);
			$this->tokenStore->saveTokens($this->userId, $result['tokens'], $account);
			$this->session->remove(AppConstants::SESSION_DEVICE);
			return new JSONResponse($this->tokenStore->getPublicAccount($this->userId));
		}
		if (($result['status'] ?? '') === 'slow_down' && isset($result['interval'])) {
			$raw['interval'] = $result['interval'];
			$this->session->set(AppConstants::SESSION_DEVICE, json_encode($raw));
		}
		return new JSONResponse($result);
	}

	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	public function startPkce(): JSONResponse {
		if ($this->userId === null) {
			return $this->error('not_signed_in', 'Not logged in', Http::STATUS_UNAUTHORIZED);
		}
		[$verifier, $challenge] = $this->oauthService->generatePkce();
		$state = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
		$this->session->set(AppConstants::SESSION_PKCE, json_encode([
			'verifier' => $verifier,
			'state' => $state,
		]));
		return new JSONResponse([
			'authorize_url' => $this->oauthService->buildAuthorizeUrl($challenge, $state),
			'redirect_uri' => AppConstants::OAUTH_REDIRECT_URI,
		]);
	}

	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	public function exchangePkce(string $callback = ''): JSONResponse {
		if ($this->userId === null) {
			return $this->error('not_signed_in', 'Not logged in', Http::STATUS_UNAUTHORIZED);
		}
		$pending = $this->sessionArray(AppConstants::SESSION_PKCE);
		if ($pending === null || !isset($pending['verifier'])) {
			return $this->error('oauth_failed', 'Start browser sign-in again');
		}
		[$code, $state, $error] = $this->oauthService->parseAuthorizationCallback($callback);
		if (in_array($error, ['access_denied', 'authorization_denied'], true)) {
			$this->session->remove(AppConstants::SESSION_PKCE);
			return $this->error('access_denied', $error ?? 'access_denied');
		}
		if ($error) {
			return $this->error('oauth_failed', $error);
		}
		if ($code === null || $code === '') {
			return $this->error('missing_code', 'Paste the full callback URL, query string, or code');
		}
		$expectedState = (string)($pending['state'] ?? '');
		if ($state !== null && $expectedState !== '' && $state !== $expectedState) {
			return $this->error('state_mismatch', 'OAuth state did not match');
		}
		try {
			$tokens = $this->oauthService->exchangeAuthorizationCode($code, (string)$pending['verifier']);
		} catch (GrokOAuthException $e) {
			return $this->error($e->getReason(), $e->getDetails());
		}
		$account = $this->oauthService->fetchUserinfo($tokens->accessToken);
		$this->tokenStore->saveTokens($this->userId, $tokens, $account);
		$this->session->remove(AppConstants::SESSION_PKCE);
		return new JSONResponse($this->tokenStore->getPublicAccount($this->userId));
	}

	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	public function logout(): JSONResponse {
		if ($this->userId === null) {
			return $this->error('not_signed_in', 'Not logged in', Http::STATUS_UNAUTHORIZED);
		}
		$tokens = $this->tokenStore->getTokens($this->userId);
		if ($tokens !== null) {
			$this->oauthService->revoke($tokens->refreshToken);
			$this->oauthService->revoke($tokens->accessToken);
		}
		$this->tokenStore->clear($this->userId);
		$this->session->remove(AppConstants::SESSION_DEVICE);
		$this->session->remove(AppConstants::SESSION_PKCE);
		return new JSONResponse(['signedIn' => false, 'email' => '', 'name' => '']);
	}

	#[NoAdminRequired]
	public function models(): JSONResponse {
		if ($this->userId === null) {
			return $this->error('not_signed_in', 'Not logged in', Http::STATUS_UNAUTHORIZED);
		}
		$defaults = [
			AppConstants::DEFAULT_CHAT_MODEL,
			AppConstants::DEFAULT_IMAGE_MODEL,
			AppConstants::DEFAULT_VOICE_MODEL,
		];
		try {
			$live = $this->clientFactory->forUser($this->userId)->listModels();
		} catch (GrokOAuthException|GrokClientException $e) {
			return new JSONResponse(['models' => $defaults]);
		}
		$models = array_values(array_unique(array_merge($defaults, $live)));
		return new JSONResponse(['models' => $models]);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function sessionArray(string $key): ?array {
		$raw = $this->session->get($key);
		if (is_array($raw)) {
			return $raw;
		}
		if (!is_string($raw) || $raw === '') {
			return null;
		}
		$decoded = json_decode($raw, true);
		return is_array($decoded) ? $decoded : null;
	}

	private function error(string $reason, string $message, int $status = Http::STATUS_BAD_REQUEST): JSONResponse {
		return new JSONResponse([
			'status' => 'error',
			'reason' => $reason,
			'message' => $message,
		], $status);
	}
}
