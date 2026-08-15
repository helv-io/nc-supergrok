<?php

declare(strict_types=1);

namespace OCA\SuperGrok\Service;

use OCA\SuperGrok\AppConstants;
use OCA\SuperGrok\Exception\GrokClientException;
use OCA\SuperGrok\Exception\GrokOAuthException;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use Psr\Log\LoggerInterface;

/**
 * Refresh-aware HTTP client for SuperGrok OAuth.
 */
class GrokClient {
	private string $chatBase;
	private string $mediaBase;
	private bool $refreshing = false;

	public function __construct(
		private IClientService $clientService,
		private OAuthService $oauthService,
		private TokenStore $tokenStore,
		private LoggerInterface $logger,
		private string $userId,
		private OAuthTokens $tokens,
	) {
		$this->chatBase = AppConstants::CHAT_API_BASES[0];
		$this->mediaBase = AppConstants::MEDIA_API_BASES[0];
	}

	public function getTokens(): OAuthTokens {
		return $this->tokens;
	}

	public function accessToken(): string {
		if (!$this->tokens->isExpired()) {
			return $this->tokens->accessToken;
		}
		if ($this->refreshing) {
			return $this->tokens->accessToken;
		}
		$this->refreshing = true;
		try {
			$this->logger->debug('Access token expired; refreshing SuperGrok session', [
				'app' => AppConstants::APP_ID,
			]);
			$refreshed = $this->oauthService->refreshTokens($this->tokens);
			$this->tokens = $refreshed;
			$this->tokenStore->saveTokens($this->userId, $refreshed);
			$this->logger->info('Refreshed SuperGrok access token (expires_at=' . $refreshed->expiresAt . ')', [
				'app' => AppConstants::APP_ID,
			]);
			return $refreshed->accessToken;
		} catch (GrokOAuthException $e) {
			$this->logger->warning('SuperGrok token refresh failed (' . $e->getReason() . '): ' . $e->getDetails(), [
				'app' => AppConstants::APP_ID,
			]);
			if (in_array($e->getReason(), ['reauth_required', 'tier_blocked'], true)) {
				throw new GrokClientException('reauth_required', $e->getDetails(), $e);
			}
			throw new GrokClientException('oauth_refresh_failed', $e->getDetails(), $e);
		} finally {
			$this->refreshing = false;
		}
	}

	/**
	 * @return list<string>
	 */
	public function listModels(): array {
		try {
			$payload = $this->requestJson('GET', AppConstants::CHAT_API_BASES, '/models', preferredBase: $this->chatBase, timeout: 20);
		} catch (GrokClientException $e) {
			$this->logger->debug('Could not list Grok models: ' . $e->getMessage(), ['app' => AppConstants::APP_ID]);
			return [];
		}
		$items = is_array($payload) && isset($payload['data']) ? $payload['data'] : $payload;
		if (!is_array($items)) {
			return [];
		}
		$ids = [];
		foreach ($items as $item) {
			if (is_string($item)) {
				$ids[] = $item;
			} elseif (is_array($item) && isset($item['id'])) {
				$ids[] = (string)$item['id'];
			}
		}
		return $ids;
	}

	/**
	 * @param list<array<string, mixed>> $messages
	 * @param list<mixed>|null $tools
	 * @return array{text: string, tool_calls: list<array<string, mixed>>, finish_reason: ?string, raw: array<string, mixed>}
	 */
	public function chat(string $model, array $messages, int $maxTokens = AppConstants::DEFAULT_MAX_TOKENS, ?float $temperature = null, ?array $tools = null): array {
		$body = [
			'model' => $model,
			'messages' => $messages,
			'stream' => false,
			'max_tokens' => $maxTokens,
		];
		if ($temperature !== null) {
			$body['temperature'] = $temperature;
		}
		if ($tools !== null && $tools !== []) {
			$body['tools'] = $tools;
			$body['tool_choice'] = 'auto';
		}
		$payload = $this->requestJson(
			'POST',
			AppConstants::CHAT_API_BASES,
			'/chat/completions',
			preferredBase: $this->chatBase,
			jsonData: $body,
			timeout: 180,
		);
		$choices = $payload['choices'] ?? [];
		if (!is_array($choices) || $choices === []) {
			throw new GrokClientException('empty_chat', 'Grok returned no chat choices');
		}
		$message = $choices[0]['message'] ?? [];
		if (!is_array($message)) {
			$message = [];
		}
		return [
			'text' => $this->messageText($message),
			'tool_calls' => $this->messageToolCalls($message),
			'finish_reason' => isset($choices[0]['finish_reason']) ? (string)$choices[0]['finish_reason'] : null,
			'raw' => $payload,
		];
	}

	/**
	 * @return list<array{url: ?string, b64_json: ?string, mime_type: string, model: string}>
	 */
	public function generateImage(string $prompt, string $model, int $n = 1, ?string $aspectRatio = null, ?string $resolution = null): array {
		$body = [
			'model' => $model,
			'prompt' => $prompt,
			'n' => $n,
			'response_format' => 'b64_json',
		];
		if ($aspectRatio !== null && $aspectRatio !== '') {
			$body['aspect_ratio'] = $aspectRatio;
		}
		if ($resolution !== null && $resolution !== '') {
			$body['resolution'] = $resolution;
		}
		$payload = $this->requestJson(
			'POST',
			AppConstants::MEDIA_API_BASES,
			AppConstants::IMAGE_PATHS[0],
			preferredBase: $this->mediaBase,
			extraPaths: array_slice(AppConstants::IMAGE_PATHS, 1),
			jsonData: $body,
			timeout: 180,
		);
		$rows = $payload['data'] ?? null;
		if (!is_array($rows) && isset($payload['url'])) {
			$rows = [$payload];
		}
		if (!is_array($rows) || $rows === []) {
			throw new GrokClientException('empty_image', 'Grok Imagine returned no images');
		}
		$results = [];
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$results[] = [
				'url' => isset($row['url']) && is_string($row['url']) ? $row['url'] : null,
				'b64_json' => isset($row['b64_json']) && is_string($row['b64_json']) ? $row['b64_json'] : null,
				'mime_type' => (string)($row['content_type'] ?? 'image/jpeg'),
				'model' => (string)($payload['model'] ?? $model),
			];
		}
		if ($results === []) {
			throw new GrokClientException('empty_image', 'Grok Imagine returned an empty image payload');
		}
		return $results;
	}

	/**
	 * @return list<array{0: string, 1: string}>
	 */
	public function listTtsVoices(): array {
		try {
			$payload = $this->requestJson(
				'GET',
				AppConstants::MEDIA_API_BASES,
				'/tts/voices',
				preferredBase: $this->mediaBase,
				timeout: 20,
			);
		} catch (GrokClientException $e) {
			$this->logger->debug('Could not list Grok voices: ' . $e->getMessage(), ['app' => AppConstants::APP_ID]);
			return AppConstants::FALLBACK_TTS_VOICES;
		}
		$voices = $payload['voices'] ?? $payload;
		if (!is_array($voices)) {
			return AppConstants::FALLBACK_TTS_VOICES;
		}
		$result = [];
		foreach ($voices as $voice) {
			if (!is_array($voice)) {
				continue;
			}
			$voiceId = $voice['voice_id'] ?? $voice['id'] ?? null;
			if (!is_string($voiceId) || $voiceId === '') {
				continue;
			}
			$result[] = [$voiceId, (string)($voice['name'] ?? $voiceId)];
		}
		return $result !== [] ? $result : AppConstants::FALLBACK_TTS_VOICES;
	}

	/**
	 * @return array{0: string, 1: string} [audio bytes, content type]
	 */
	public function tts(string $text, string $voiceId, string $language = 'en', string $codec = 'mp3', int $sampleRate = 24000, float $speed = 1.0): array {
		$body = [
			'text' => $text,
			'voice_id' => $voiceId,
			'language' => AppConstants::mapTtsLanguage($language),
			'speed' => $speed,
			'output_format' => [
				'codec' => $codec,
				'sample_rate' => $sampleRate,
			],
		];
		try {
			$payload = $this->requestJson(
				'POST',
				AppConstants::MEDIA_API_BASES,
				'/tts',
				preferredBase: $this->mediaBase,
				jsonData: $body,
				timeout: 120,
			);
			if (isset($payload['audio']) && is_string($payload['audio'])) {
				$audio = base64_decode($payload['audio'], true);
				if ($audio === false) {
					throw new GrokClientException('empty_tts', 'Grok TTS returned invalid audio');
				}
				$contentType = (string)($payload['content_type'] ?? $this->contentTypeForCodec($codec));
				return [$audio, $contentType];
			}
		} catch (GrokClientException $e) {
			$this->logger->debug('Grok TTS JSON path failed, trying raw audio: ' . $e->getMessage(), [
				'app' => AppConstants::APP_ID,
			]);
		}

		[$raw, $contentType] = $this->requestRaw(
			'POST',
			AppConstants::MEDIA_API_BASES,
			'/tts',
			preferredBase: $this->mediaBase,
			jsonData: $body,
			timeout: 120,
		);
		if ($raw === '') {
			throw new GrokClientException('empty_tts', 'Grok TTS returned no audio');
		}
		return [$raw, $contentType !== '' ? $contentType : $this->contentTypeForCodec($codec)];
	}

	/**
	 * Transcribe a finished clip via POST https://api.x.ai/v1/stt.
	 * Do not use the realtime websocket for buffered clips.
	 */
	public function stt(string $audio, string $filename, string $contentType, ?string $language = null): string {
		$lang = $language !== null && $language !== '' && $language !== 'default' && $language !== 'detect_language'
			? AppConstants::mapTtsLanguage($language)
			: 'en';
		$this->accessToken();
		$options = [
			'headers' => $this->headers(jsonBody: false, base: 'https://api.x.ai/v1'),
			'multipart' => [
				['name' => 'vad_threshold', 'contents' => '0'],
				['name' => 'language', 'contents' => $lang],
				['name' => 'format', 'contents' => 'true'],
				[
					'name' => 'file',
					'contents' => $audio,
					'filename' => $filename !== '' ? $filename : 'speech.wav',
					'headers' => ['Content-Type' => $contentType !== '' ? $contentType : 'audio/wav'],
				],
			],
			'timeout' => 120,
			'http_errors' => false,
		];
		try {
			$response = $this->clientService->newClient()->post('https://api.x.ai/v1/stt', $options);
			$status = $response->getStatusCode();
			$body = $this->bodyToString($response->getBody());
		} catch (\Throwable $e) {
			$extracted = $this->responseFromException($e);
			if ($extracted === null) {
				throw new GrokClientException('stt_failed', $e->getMessage(), $e);
			}
			$status = $extracted->getStatusCode();
			$body = $this->bodyToString($extracted->getBody());
		}

		if (in_array($status, [401, 403], true)) {
			$this->tokens->expiresAt = 0;
			$this->accessToken();
			$options['headers'] = $this->headers(jsonBody: false, base: 'https://api.x.ai/v1');
			try {
				$response = $this->clientService->newClient()->post('https://api.x.ai/v1/stt', $options);
				$status = $response->getStatusCode();
				$body = $this->bodyToString($response->getBody());
			} catch (\Throwable $e) {
				throw new GrokClientException('stt_failed', $e->getMessage(), $e);
			}
		}

		if ($status >= 400) {
			throw new GrokClientException('stt_failed', 'Grok STT failed (' . $status . '): ' . substr($body, 0, 200));
		}
		$payload = json_decode($body, true);
		$text = $this->extractTranscript($payload);
		if ($text === null || $text === '') {
			throw new GrokClientException('stt_empty', 'Grok STT heard audio but returned no text');
		}
		return $text;
	}

	/**
	 * Mint an ephemeral Realtime client secret. Gated by REALTIME_ENABLED.
	 *
	 * @return array<string, mixed>
	 */
	public function createRealtimeClientSecret(string $model, int $expiresSeconds = 600): array {
		$this->assertRealtimeEnabled();
		$payload = $this->requestJson(
			'POST',
			AppConstants::MEDIA_API_BASES,
			'/realtime/client_secrets',
			preferredBase: $this->mediaBase,
			jsonData: [
				'expires_after' => ['seconds' => min(max($expiresSeconds, 30), 3600)],
				'session' => ['model' => $model],
			],
			timeout: 30,
		);
		if (!isset($payload['value'])) {
			throw new GrokClientException('realtime_secret', 'Grok Realtime did not return a client secret');
		}
		return $payload;
	}

	/**
	 * One text turn over the Realtime websocket. Gated by REALTIME_ENABLED.
	 *
	 * @return array{text: string}
	 */
	public function realtimeText(string $model, string $instructions, string $userText, string $voice = 'eve'): array {
		$this->assertRealtimeEnabled();
		// The HA client talks to REALTIME_WS_URLS with session.update /
		// conversation.item.create / response.create. Nextcloud 0.1.0 keeps
		// that helper in the tree but does not open a websocket.
		unset($model, $instructions, $userText, $voice);
		throw new GrokClientException(
			'realtime_unavailable',
			'Realtime websocket helper is not wired in this Nextcloud build',
		);
	}

	public function downloadBytes(string $url): string {
		$this->accessToken();
		$base = str_contains($url, 'grok.com') ? 'https://cli-chat-proxy.grok.com/v1' : 'https://api.x.ai/v1';
		$options = [
			'headers' => $this->headers(jsonBody: false, base: $base),
			'timeout' => 120,
			'http_errors' => false,
		];
		try {
			$response = $this->clientService->newClient()->get($url, $options);
		} catch (\Throwable $e) {
			throw new GrokClientException('download_failed', $e->getMessage(), $e);
		}
		if ($response->getStatusCode() >= 400) {
			throw new GrokClientException('download_failed', 'Image download failed (' . $response->getStatusCode() . ')');
		}
		return $this->bodyToString($response->getBody());
	}

	private function assertRealtimeEnabled(): void {
		if (!AppConstants::REALTIME_ENABLED) {
			throw new GrokClientException('realtime_disabled', 'Realtime is withheld from this release');
		}
	}

	/**
	 * @param list<string> $bases
	 * @param list<string> $extraPaths
	 * @param array<string, mixed>|null $jsonData
	 * @return array<string, mixed>
	 */
	private function requestJson(
		string $method,
		array $bases,
		string $path,
		?string $preferredBase = null,
		?array $jsonData = null,
		int $timeout = 120,
		array $extraPaths = [],
	): array {
		[$body] = $this->request($method, $bases, $path, $preferredBase, $jsonData, $timeout, $extraPaths, false);
		if ($body === '') {
			return [];
		}
		$parsed = json_decode($body, true);
		if (!is_array($parsed)) {
			throw new GrokClientException('bad_json', 'Grok returned non-JSON');
		}
		return $parsed;
	}

	/**
	 * @param list<string> $bases
	 * @param list<string> $extraPaths
	 * @param array<string, mixed>|null $jsonData
	 * @return array{0: string, 1: string}
	 */
	private function requestRaw(
		string $method,
		array $bases,
		string $path,
		?string $preferredBase = null,
		?array $jsonData = null,
		int $timeout = 120,
		array $extraPaths = [],
	): array {
		return $this->request($method, $bases, $path, $preferredBase, $jsonData, $timeout, $extraPaths, true);
	}

	/**
	 * @param list<string> $bases
	 * @param list<string> $extraPaths
	 * @param array<string, mixed>|null $jsonData
	 * @return array{0: string, 1: string}
	 */
	private function request(
		string $method,
		array $bases,
		string $path,
		?string $preferredBase,
		?array $jsonData,
		int $timeout,
		array $extraPaths,
		bool $raw,
	): array {
		$this->accessToken();
		$ordered = [];
		if ($preferredBase !== null && $preferredBase !== '') {
			$ordered[] = $preferredBase;
		}
		foreach ($bases as $base) {
			if (!in_array($base, $ordered, true)) {
				$ordered[] = $base;
			}
		}
		$paths = array_merge([$path], $extraPaths);
		$lastStatus = 0;
		$lastBody = '';
		$lastError = '';

		foreach ($ordered as $base) {
			foreach ($paths as $candidate) {
				$url = rtrim($base, '/') . $candidate;
				try {
					[$status, $body, $contentType] = $this->send($method, $url, $base, $jsonData, $timeout);
					if (in_array($status, [401, 403], true) && $base === $ordered[0] && $candidate === $paths[0]) {
						$this->logger->info('Grok ' . $method . ' ' . $url . ' -> ' . $status . '; refreshing token and retrying', [
							'app' => AppConstants::APP_ID,
						]);
						$this->tokens->expiresAt = 0;
						$this->accessToken();
						[$status, $body, $contentType] = $this->send($method, $url, $base, $jsonData, $timeout);
					}
					if ($status === 404) {
						$lastStatus = $status;
						$lastBody = substr($body, 0, 200);
						continue;
					}
					if ($status >= 400) {
						$lastStatus = $status;
						$lastBody = substr($body, 0, 200);
						$this->logger->warning('Grok ' . $method . ' ' . $url . ' -> ' . $status . ': ' . $lastBody, [
							'app' => AppConstants::APP_ID,
						]);
						if (in_array($status, [401, 402, 403], true)) {
							$lastError = 'Grok rejected the request (' . $status . '): ' . $lastBody;
							continue;
						}
						throw new GrokClientException('http_' . $status, 'Grok request failed (' . $status . '): ' . $lastBody);
					}
					$this->rememberBase($base, $bases);
					return [$body, $contentType];
				} catch (GrokClientException $e) {
					if (str_starts_with($e->getReason(), 'http_')) {
						throw $e;
					}
					$lastError = $e->getMessage();
					$this->logger->warning('Grok transport error on ' . $method . ' ' . $url . ': ' . $e->getMessage(), [
						'app' => AppConstants::APP_ID,
					]);
				}
			}
		}

		if (in_array($lastStatus, [401, 403], true)) {
			throw new GrokClientException('reauth_required', $lastBody !== '' ? $lastBody : 'SuperGrok OAuth token was rejected');
		}
		throw new GrokClientException(
			'request_failed',
			'Grok request failed on every endpoint (' . ($lastStatus !== 0 ? (string)$lastStatus : 'network') . '): ' . ($lastBody !== '' ? $lastBody : $lastError),
		);
	}

	/**
	 * @param array<string, mixed>|null $jsonData
	 * @return array{0: int, 1: string, 2: string}
	 */
	private function send(string $method, string $url, string $base, ?array $jsonData, int $timeout): array {
		$options = [
			'headers' => $this->headers(jsonBody: $jsonData !== null, base: $base),
			'timeout' => $timeout,
			'http_errors' => false,
		];
		if ($jsonData !== null) {
			$options['body'] = json_encode($jsonData, JSON_THROW_ON_ERROR);
		}
		$client = $this->clientService->newClient();
		try {
			if (strtoupper($method) === 'GET') {
				$response = $client->get($url, $options);
			} else {
				$response = $client->post($url, $options);
			}
		} catch (\Throwable $e) {
			$extracted = $this->responseFromException($e);
			if ($extracted === null) {
				throw new GrokClientException('network', $e->getMessage(), $e);
			}
			$response = $extracted;
		}
		return [
			$response->getStatusCode(),
			$this->bodyToString($response->getBody()),
			(string)$response->getHeader('Content-Type'),
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function headers(bool $jsonBody, ?string $base = null): array {
		$headers = [
			'Authorization' => 'Bearer ' . $this->tokens->accessToken,
			'Accept' => 'application/json',
			'User-Agent' => AppConstants::USER_AGENT,
		];
		if ($base !== null && str_contains($base, 'grok.com')) {
			foreach (AppConstants::GROK_CLI_HEADERS as $name => $value) {
				$headers[$name] = $value;
			}
		}
		if ($jsonBody) {
			$headers['Content-Type'] = 'application/json';
		}
		return $headers;
	}

	/**
	 * @param list<string> $bases
	 */
	private function rememberBase(string $base, array $bases): void {
		if (in_array($base, AppConstants::CHAT_API_BASES, true) || $bases === AppConstants::CHAT_API_BASES) {
			$this->chatBase = $base;
		} else {
			$this->mediaBase = $base;
		}
	}

	/**
	 * @param array<string, mixed> $message
	 * @return list<array<string, mixed>>
	 */
	private function messageToolCalls(array $message): array {
		$raw = $message['tool_calls'] ?? [];
		if (!is_array($raw)) {
			return [];
		}
		$calls = [];
		foreach ($raw as $call) {
			if (!is_array($call)) {
				continue;
			}
			$function = is_array($call['function'] ?? null) ? $call['function'] : [];
			$argsRaw = $function['arguments'] ?? '{}';
			$args = is_string($argsRaw) ? json_decode($argsRaw, true) : $argsRaw;
			$calls[] = [
				'name' => (string)($function['name'] ?? 'unknown'),
				'type' => 'tool_call',
				'id' => (string)($call['id'] ?? ('call_' . count($calls))),
				'args' => is_array($args) ? $args : ['value' => $args],
			];
		}
		return $calls;
	}

	/**
	 * @param array<string, mixed> $message
	 */
	private function messageText(array $message): string {
		$content = $message['content'] ?? '';
		if (is_string($content)) {
			return $content;
		}
		if (!is_array($content)) {
			return '';
		}
		$parts = [];
		foreach ($content as $part) {
			if (is_string($part)) {
				$parts[] = $part;
			} elseif (is_array($part) && isset($part['text']) && is_string($part['text'])) {
				$parts[] = $part['text'];
			}
		}
		return implode('', $parts);
	}

	private function extractTranscript(mixed $payload): ?string {
		if (is_string($payload) && trim($payload) !== '') {
			return trim($payload);
		}
		if (!is_array($payload)) {
			return null;
		}
		foreach (['text', 'transcript', 'transcription'] as $key) {
			$value = $payload[$key] ?? null;
			if (is_string($value) && trim($value) !== '') {
				return trim($value);
			}
		}
		if (isset($payload['data']) && is_array($payload['data'])) {
			$nested = $this->extractTranscript($payload['data']);
			if ($nested !== null) {
				return $nested;
			}
			if ($payload['data'] !== [] && array_is_list($payload['data'])) {
				$nested = $this->extractTranscript($payload['data'][0]);
				if ($nested !== null) {
					return $nested;
				}
			}
		}
		$words = $payload['words'] ?? null;
		if (is_array($words)) {
			$joined = [];
			foreach ($words as $word) {
				if (is_array($word) && isset($word['text'])) {
					$joined[] = trim((string)$word['text']);
				}
			}
			$text = trim(implode(' ', array_filter($joined, static fn (string $part): bool => $part !== '')));
			if ($text !== '') {
				return $text;
			}
		}
		return null;
	}

	private function contentTypeForCodec(string $codec): string {
		return match ($codec) {
			'mp3' => 'audio/mpeg',
			'wav' => 'audio/wav',
			'pcm' => 'audio/l16',
			'mulaw' => 'audio/basic',
			'alaw' => 'audio/PCMA',
			default => 'application/octet-stream',
		};
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
}
