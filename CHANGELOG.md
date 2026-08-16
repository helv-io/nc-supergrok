# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

The app version is `appinfo/info.xml` → `version`.

## [0.1.1] - 2026-08-16

### Fixed

- Device and browser sign-in POSTs returned HTML 500 (`Could not resolve OauthController`). Nextcloud maps `oauth#` to `OauthController`; the class is `OAuthController`, so routes now use `o_auth#`.
- Personal settings shows a status code instead of a JSON parse error when the server returns HTML.

## [0.1.0] - 2026-08-15

### Added

- Nextcloud SuperGrok OAuth app (`supergrok`). Conversation, Voice, Imagine. Sign in with SuperGrok / X Premium+. No API key. SpaceXAI.
- Device-code sign-in as the default. Browser PKCE paste-callback (`http://127.0.0.1:56121/callback`) as the backup.
- Per-user tokens in `ICredentialsManager` (`supergrok_oauth`), with rotating refresh and 120s skew.
- Task Processing providers: TextToText, TextToTextChat, TextToImage, AudioToText, TextToSpeech, plus TextToTextChatWithTools when the OCP class exists.
- Thin Assistant wrappers: summary, headline, topics, reformulation, change tone, proofread, context write, emoji, translate.
- Personal settings (sign in / sign out / account email) and Admin settings (default models, enable/disable providers) on the `ai` section.
- Realtime helpers stay in the client behind `REALTIME_ENABLED = false`. No Realtime UI.
