<?php

declare(strict_types=1);

namespace OCA\SuperGrok;

/**
 * Constants for SuperGrok OAuth.
 * OAuth values match helv-io/ha-supergrok custom_components/grok_oauth/const.py.
 */
final class AppConstants {
	public const APP_ID = 'supergrok';
	public const APP_NAME = 'SuperGrok OAuth';

	/**
	 * Realtime is withheld from this release. Flip to true to restore
	 * client helpers that mint a Realtime session. Keep the helpers in
	 * the tree either way.
	 */
	public const REALTIME_ENABLED = false;

	/** SuperGrok / Grok CLI public OAuth client (no secret). */
	public const OAUTH_CLIENT_ID = 'b1a00492-073a-47ea-816f-4c329264a828';
	public const OAUTH_ISSUER = 'https://auth.x.ai';
	public const OAUTH_AUTHORIZE_URL = 'https://auth.x.ai/oauth2/authorize';
	public const OAUTH_DEVICE_URL = 'https://auth.x.ai/oauth2/device/code';
	public const OAUTH_TOKEN_URL = 'https://auth.x.ai/oauth2/token';
	/** Only redirect registered on the public Grok CLI client. */
	public const OAUTH_REDIRECT_URI = 'http://127.0.0.1:56121/callback';
	public const OAUTH_USERINFO_URL = 'https://auth.x.ai/oauth2/userinfo';
	public const OAUTH_REVOKE_URL = 'https://auth.x.ai/oauth2/revoke';
	public const OAUTH_SCOPE = 'openid profile email offline_access grok-cli:access api:access conversations:read conversations:write';
	public const OAUTH_DEVICE_GRANT = 'urn:ietf:params:oauth:grant-type:device_code';

	/** Subscription OAuth traffic belongs on the Grok CLI proxy first. */
	public const CHAT_API_BASES = [
		'https://cli-chat-proxy.grok.com/v1',
		'https://api.x.ai/v1',
	];
	public const MEDIA_API_BASES = [
		'https://api.x.ai/v1',
		'https://cli-chat-proxy.grok.com/v1',
	];
	public const REALTIME_WS_URLS = [
		'wss://api.x.ai/v1/realtime',
		'wss://cli-chat-proxy.grok.com/v1/realtime',
	];

	public const GROK_CLI_HEADERS = [
		'x-xai-token-auth' => 'xai-grok-cli',
		'x-grok-client-identifier' => 'grok-shell',
		'x-grok-client-version' => '0.2.93',
	];

	/** ICredentialsManager identifier. userId '' is the admin/instance slot. */
	public const CREDENTIALS_IDENTIFIER = 'supergrok_oauth';

	public const APP_CHAT_MODEL = 'chat_model';
	public const APP_IMAGE_MODEL = 'image_model';
	public const APP_VOICE_MODEL = 'voice_model';
	public const APP_TTS_VOICE = 'tts_voice';
	public const APP_ENABLE_TEXT = 'enable_text';
	public const APP_ENABLE_IMAGE = 'enable_image';
	public const APP_ENABLE_STT = 'enable_stt';
	public const APP_ENABLE_TTS = 'enable_tts';

	public const DEFAULT_CHAT_MODEL = 'grok-4.6';
	public const DEFAULT_IMAGE_MODEL = 'grok-imagine-image-2.0';
	public const DEFAULT_VOICE_MODEL = 'grok-voice-think-fast-2.0';
	public const DEFAULT_REALTIME_MODEL = 'grok-voice-latest';
	public const DEFAULT_TTS_VOICE = 'eve';
	public const DEFAULT_MAX_TOKENS = 4096;

	public const IMAGE_PATHS = [
		'/images/generations',
		'/image/generations',
	];

	public const TOKEN_EXPIRY_SKEW_SECONDS = 120;
	public const USER_AGENT = 'Nextcloud SuperGrok OAuth';

	public const SESSION_DEVICE = 'supergrok_device';
	public const SESSION_PKCE = 'supergrok_pkce';

	public const FALLBACK_TTS_VOICES = [
		['eve', 'Eve'],
		['ara', 'Ara'],
		['leo', 'Leo'],
		['rex', 'Rex'],
		['sal', 'Sal'],
		['luna', 'Luna'],
		['orion', 'Orion'],
		['iris', 'Iris'],
		['atlas', 'Atlas'],
		['helix', 'Helix'],
		['celeste', 'Celeste'],
		['sirius', 'Sirius'],
	];

	public const TTS_LANGUAGE_MAP = [
		'en' => 'en',
		'en-US' => 'en',
		'en-GB' => 'en',
		'en-AU' => 'en',
		'ar' => 'ar-SA',
		'ar-SA' => 'ar-SA',
		'fr' => 'fr',
		'de' => 'de',
		'es' => 'es-ES',
		'it' => 'it',
		'ja' => 'ja',
		'ko' => 'ko',
		'pt' => 'pt-BR',
		'pt-BR' => 'pt-BR',
		'zh' => 'zh',
		'hi' => 'hi',
	];

	public static function mapTtsLanguage(string $language): string {
		if (isset(self::TTS_LANGUAGE_MAP[$language])) {
			return self::TTS_LANGUAGE_MAP[$language];
		}
		$short = strtolower(substr($language, 0, 2));
		return self::TTS_LANGUAGE_MAP[$short] ?? 'en';
	}
}
